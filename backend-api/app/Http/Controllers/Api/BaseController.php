<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AuthHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseController extends Controller
{
    /**
     * Success response method.
     *
     * @param mixed $result
     * @param string $message
     * @param int $code
     * @return JsonResponse
     */
    protected function sendSuccess($result, string $message = 'Success', int $code = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $result
        ];

        return response()->json($response, $code);
    }

    /**
     * Error response method.
     *
     * @param string $error
     * @param array $errorMessages
     * @param int $code
     * @return JsonResponse
     */
    protected function sendError(string $error, array $errorMessages = [], int $code = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $error,
        ];

        if (!empty($errorMessages)) {
            $response['errors'] = $errorMessages;
        }

        return response()->json($response, $code);
    }

    /**
     * Validate request with given rules.
     *
     * @param Request $request
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return array|JsonResponse
     */
    protected function validateRequest(Request $request, array $rules, array $messages = [], array $customAttributes = [])
    {
        $validator = Validator::make($request->all(), $rules, $messages, $customAttributes);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors()->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $validator->validated();
    }

    /**
     * Get authenticated user.
     *
     * @return mixed
     */
    protected function getUser()
    {
        return AuthHelper::user();
    }

    /**
     * Get authenticated user ID.
     *
     * @return int|null
     */
    protected function getUserId(): ?int
    {
        return AuthHelper::userId();
    }

    /**
     * Check if user has role.
     *
     * @param string $role
     * @return bool
     */
    protected function hasRole(string $role): bool
    {
        return AuthHelper::hasRole($role);
    }

    /**
     * Check if user has permission.
     *
     * @param string $permission
     * @return bool
     */
    protected function hasPermission(string $permission): bool
    {
        return AuthHelper::hasPermission($permission);
    }

    /**
     * Handle try-catch for database operations.
     *
     * @param callable $callback
     * @param string $successMessage
     * @param string $errorMessage
     * @return JsonResponse
     */
    protected function handleDbOperation(callable $callback, string $successMessage = 'Operation successful', string $errorMessage = 'Operation failed'): JsonResponse
    {
        try {
            $result = $callback();
            return $this->sendSuccess($result, $successMessage);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError($errorMessage);
        }
    }

    /**
     * Handle file upload.
     *
     * @param Request $request
     * @param string $field
     * @param string $directory
     * @param array $allowedTypes
     * @param int $maxSize
     * @return array|JsonResponse
     */
    protected function handleFileUpload(Request $request, string $field, string $directory, array $allowedTypes = [], int $maxSize = 5242880)
    {
        if (!$request->hasFile($field)) {
            return $this->sendError('File not found', ['error' => "The {$field} field is required."]);
        }

        $file = $request->file($field);

        // Validate file type
        if (!empty($allowedTypes) && !in_array($file->getClientMimeType(), $allowedTypes)) {
            return $this->sendError('Invalid file type', ['error' => 'The file must be of type: ' . implode(', ', $allowedTypes)]);
        }

        // Validate file size
        if ($file->getSize() > $maxSize) {
            return $this->sendError('File too large', ['error' => 'The file size must not exceed ' . ($maxSize / 1024 / 1024) . 'MB']);
        }

        try {
            $path = $file->store($directory, 'public');
            return [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'name' => $file->getClientOriginalName(),
                'type' => $file->getClientMimeType(),
                'size' => $file->getSize()
            ];
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('File upload failed');
        }
    }

    /**
     * Handle pagination parameters.
     *
     * @param Request $request
     * @return array
     */
    protected function getPaginationParams(Request $request): array
    {
        return [
            'page' => $request->input('page', 1),
            'per_page' => min($request->input('per_page', 15), 100),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => in_array($request->input('sort_order'), ['asc', 'desc']) ? $request->input('sort_order') : 'desc',
            'search' => $request->input('search'),
            'filters' => $request->input('filters', [])
        ];
    }

    /**
     * Apply common query filters.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyQueryFilters($query, array $filters)
    {
        foreach ($filters as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return $query;
    }

    /**
     * Handle export operation.
     *
     * @param callable $callback
     * @param string $filename
     * @param string $format
     * @return mixed
     */
    protected function handleExport(callable $callback, string $filename, string $format = 'csv')
    {
        try {
            return $callback();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return $this->sendError('Export failed');
        }
    }
}
