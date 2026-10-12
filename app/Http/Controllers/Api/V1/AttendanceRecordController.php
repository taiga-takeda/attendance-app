<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceRecordController extends Controller
{
    /**
     * AP01: 勤怠一覧API
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $perPage = min(max((int)$request->query('per_page', 20), 1), 100);

        $records = AttendanceRecord::with(['user', 'breaks'])
            ->when($request->query('user_id'), function ($query, $userId) {
                return $query->where('user_id', $userId);
            })
            ->when($request->query('date'), function ($query, $date) {
                return $query->where('date', $date);
            })
            ->when($request->query('month'), function ($query, $month) {
                return $query->where('date', 'like', $month . '%');
            })
            ->latest('date')
            ->paginate($perPage);

        return AttendanceRecordResource::collection($records);
    }

    /**
     * AP02: 勤怠詳細API
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->load(['user', 'breaks', 'applications']);
        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * AP03: 勤怠新規登録API
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $attendanceRecord = $request->user()->attendanceRecords()->create($request->validated());
        $attendanceRecord->load(['user', 'breaks']);

        return (new AttendanceRecordResource($attendanceRecord))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * AP04: 勤怠更新API
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        if ($request->user()->cannot('update', $attendanceRecord)) {
            abort(response()->json(['error' => 'この操作を実行する権限がありません。'], 403));
        }

        $attendanceRecord->update($request->validated());
        $attendanceRecord->load(['user', 'breaks']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * AP05: 勤怠削除API
     */
    public function destroy(AttendanceRecord $attendanceRecord): JsonResponse
    {
        if (auth()->user()->cannot('delete', $attendanceRecord)) {
            abort(response()->json(['error' => 'この操作を実行する権限がありません。'], 403));
        }

        $attendanceRecord->delete();

        return response()->json(null, 204);
    }
}
