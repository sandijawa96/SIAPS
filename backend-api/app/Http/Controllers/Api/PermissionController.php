<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use App\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('module')
            ->orderBy('name')
            ->get();
        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function getByModule()
    {
        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->orderBy('module')
            ->orderBy('name')
            ->get()
            ->groupBy('module');
        return response()->json(['success' => true, 'data' => $permissions]);
    }

    public function getModules()
    {
        $modules = Permission::query()
            ->where('guard_name', 'web')
            ->distinct('module')
            ->pluck('module');
        return response()->json(['success' => true, 'data' => $modules]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:permissions,name',
            'display_name' => 'required|string',
            'module' => 'required|string'
        ]);

        if (!in_array($request->name, PermissionCatalog::names(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission harus terdaftar di PermissionCatalog agar konsisten dengan fitur sistem'
            ], 422);
        }

        $permission = Permission::create([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'module' => $request->module,
            'guard_name' => 'web'
        ]);

        return response()->json(['success' => true, 'data' => $permission], 201);
    }

    public function show($id)
    {
        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->findOrFail($id);
        return response()->json(['success' => true, 'data' => $permission]);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => 'required|string|unique:permissions,name,' . $id,
            'display_name' => 'required|string',
            'module' => 'required|string'
        ]);

        if (!in_array($request->name, PermissionCatalog::names(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission harus terdaftar di PermissionCatalog agar konsisten dengan fitur sistem'
            ], 422);
        }

        $permission->update([
            'name' => $request->name,
            'display_name' => $request->display_name,
            'module' => $request->module
        ]);

        return response()->json(['success' => true, 'data' => $permission]);
    }

    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        if (in_array($permission->name, PermissionCatalog::names(), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Permission katalog inti tidak dapat dihapus dari API. Ubah melalui PermissionCatalog.'
            ], 422);
        }

        $permission->delete();

        return response()->json(['success' => true, 'message' => 'Permission deleted successfully']);
    }
}
