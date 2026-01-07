<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\SkillStoreRequest;
use App\Http\Requests\SkillUpdateRequest;
use App\Http\Responses\ApiResponse;
use App\Http\Services\SkillService;
use Illuminate\Http\Request;

class SkillController extends Controller
{
    public function __construct(
        private SkillService $skillService
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $data = $this->skillService->index();
        return ApiResponse::success($data, 'Skills retrieved successfully');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SkillStoreRequest $request)
    {
        $data = $this->skillService->store($request->validated());
        return ApiResponse::success($data, 'Skill created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $data = $this->skillService->show($id);
        return ApiResponse::success($data, 'Skill retrieved successfully');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SkillUpdateRequest $request, string $id)
    {
        $data = $this->skillService->update($request->validated(), $id);
        return ApiResponse::success($data, 'Skill updated successfully');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $data = $this->skillService->delete($id);
        return ApiResponse::success($data, 'Skill deleted successfully');
    }
}
