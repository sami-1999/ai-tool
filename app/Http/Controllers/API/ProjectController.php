<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\ProjectService;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $data = $this->projectService->getUserProjects($userId);
        return ApiResponse::success($data, 'Projects retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProjectStoreRequest $request)
    {
        $data = array_merge($request->validated(), ['user_id' => $request->user()->id]);
        $project = $this->projectService->store($data);
        return ApiResponse::success($project, 'Project created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->projectService->show($id);
        return ApiResponse::success($data, 'Project retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectUpdateRequest $request, string $id)
    {
        $data = $this->projectService->update($request->validated(), $id);
        return ApiResponse::success($data, 'Project updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $data = $this->projectService->delete($id);
        return ApiResponse::success($data, 'Project deleted successfully');
    }
}
