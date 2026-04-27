<?php

namespace App\Console\Commands;

use App\Support\PermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class SyncPermissionCatalog extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:sync-catalog
        {--guard=web : Guard name to synchronize}
        {--execute : Apply changes to database. Without this, command runs in dry-run mode}
        {--prune : Remove permissions outside the active catalog whitelist}
        {--prune-used : Also remove legacy permissions that are still assigned to roles/models}
        {--force : Required when pruning on production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize active permission catalog and optionally prune legacy permissions.';

    public function handle(): int
    {
        $guard = (string) $this->option('guard');
        $execute = (bool) $this->option('execute');
        $prune = (bool) $this->option('prune');
        $pruneUsed = (bool) $this->option('prune-used');
        $dryRun = !$execute;

        if ($dryRun) {
            $this->warn('Dry-run mode enabled. No database changes will be written.');
        }

        $catalogDefinitions = collect(PermissionCatalog::definitions())
            ->keyBy('name');

        $existingPermissions = Permission::query()
            ->where('guard_name', $guard)
            ->get()
            ->keyBy('name');

        $toCreateNames = $catalogDefinitions->keys()
            ->diff($existingPermissions->keys())
            ->values();

        $toUpdate = collect();
        foreach ($catalogDefinitions as $name => $definition) {
            $existing = $existingPermissions->get($name);
            if (!$existing) {
                continue;
            }

            $changedFields = [];
            foreach (['display_name', 'description', 'module'] as $field) {
                if ((string) ($existing->{$field} ?? '') !== (string) ($definition[$field] ?? '')) {
                    $changedFields[] = $field;
                }
            }

            if ($changedFields !== []) {
                $toUpdate->push([
                    'name' => $name,
                    'changed_fields' => $changedFields,
                ]);
            }
        }

        $legacyNames = $existingPermissions->keys()
            ->diff($catalogDefinitions->keys())
            ->values();

        $legacyUsage = collect();
        if ($legacyNames->isNotEmpty()) {
            $roleUsageTable = config('permission.table_names.role_has_permissions', 'role_has_permissions');
            $modelUsageTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');

            foreach ($legacyNames as $name) {
                $permission = $existingPermissions->get($name);
                $roleUsageCount = DB::table($roleUsageTable)
                    ->where('permission_id', $permission->id)
                    ->count();
                $directUsageCount = DB::table($modelUsageTable)
                    ->where('permission_id', $permission->id)
                    ->count();

                $legacyUsage->put($name, [
                    'roles' => $roleUsageCount,
                    'direct' => $directUsageCount,
                ]);
            }
        }

        $legacyUsed = $legacyUsage
            ->filter(static fn (array $usage): bool => ($usage['roles'] + $usage['direct']) > 0)
            ->keys()
            ->values();

        $legacyUnused = $legacyUsage
            ->filter(static fn (array $usage): bool => ($usage['roles'] + $usage['direct']) === 0)
            ->keys()
            ->values();

        $deletionCandidates = collect();
        if ($prune) {
            $deletionCandidates = $pruneUsed ? $legacyNames : $legacyUnused;
        }

        $this->info("Guard: {$guard}");
        $this->line('Catalog entries: ' . $catalogDefinitions->count());
        $this->line('Existing permissions: ' . $existingPermissions->count());
        $this->line('Create: ' . $toCreateNames->count());
        $this->line('Update: ' . $toUpdate->count());
        $this->line('Legacy (outside catalog): ' . $legacyNames->count());
        $this->line('Legacy used by roles/models: ' . $legacyUsed->count());
        $this->line('Legacy unused: ' . $legacyUnused->count());
        if ($prune) {
            $this->line('Legacy to delete (current mode): ' . $deletionCandidates->count());
        }

        if ($toCreateNames->isNotEmpty()) {
            $this->newLine();
            $this->info('Permissions to create:');
            foreach ($toCreateNames as $name) {
                $this->line("- {$name}");
            }
        }

        if ($toUpdate->isNotEmpty()) {
            $this->newLine();
            $this->info('Permissions to update:');
            foreach ($toUpdate as $row) {
                $this->line("- {$row['name']} (fields: " . implode(', ', $row['changed_fields']) . ')');
            }
        }

        if ($legacyNames->isNotEmpty()) {
            $this->newLine();
            $this->info('Legacy permissions found:');
            foreach ($legacyNames as $name) {
                $usage = $legacyUsage->get($name, ['roles' => 0, 'direct' => 0]);
                $this->line("- {$name} (roles: {$usage['roles']}, direct-models: {$usage['direct']})");
            }
        }

        if ($legacyNames->isNotEmpty() && !$prune) {
            $this->newLine();
            $this->warn('Legacy permissions are not deleted because --prune was not provided.');
        }

        if ($prune && !$pruneUsed && $legacyUsed->isNotEmpty()) {
            $this->newLine();
            $this->warn('Some legacy permissions are still used and will NOT be deleted in safe prune mode.');
            $this->warn('Use --prune-used if you intentionally want to delete used legacy permissions.');
        }

        if ($dryRun) {
            $this->newLine();
            $this->info('Dry-run completed.');
            return self::SUCCESS;
        }

        if ($prune && app()->environment('production') && !$this->option('force')) {
            $this->error('Refusing to prune permissions in production without --force.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($guard, $catalogDefinitions, $deletionCandidates, $prune): void {
            foreach ($catalogDefinitions as $name => $definition) {
                Permission::query()->updateOrCreate(
                    ['name' => $name, 'guard_name' => $guard],
                    [
                        'display_name' => $definition['display_name'],
                        'description' => $definition['description'],
                        'module' => $definition['module'],
                        'guard_name' => $guard,
                    ]
                );
            }

            if ($prune && $deletionCandidates->isNotEmpty()) {
                Permission::query()
                    ->where('guard_name', $guard)
                    ->whereIn('name', $deletionCandidates->all())
                    ->delete();
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('Permission catalog synchronization finished successfully.');

        return self::SUCCESS;
    }
}
