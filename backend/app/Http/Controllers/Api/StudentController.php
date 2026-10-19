<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ResourceInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\StudyGroup;
use App\Services\QueueStatus;
use App\Services\QueueStatusResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function __construct(private QueueStatusResolver $queue) {}

    /**
     * With course_id the whole group is returned (no paging) with each student's
     * queue status for that course, and queue_state filters by it.
     */
    public function index(Request $request, StudyGroup $group): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Student::class, $group]);
        $request->validate([
            'course_id' => ['sometimes', 'integer'],
            'queue_state' => ['sometimes', Rule::in([QueueStatus::NEVER_ASKED, QueueStatus::QUEUE, QueueStatus::DEBTOR, QueueStatus::RESTING])],
        ]);

        $query = $group->students()
            ->when(! $request->boolean('include_inactive'), fn ($inner) => $inner->active())
            ->when($request->filled('q'), fn ($inner) => $inner->where('full_name', 'like', '%'.$request->string('q').'%'))
            ->orderBy('full_name');

        if (! $request->filled('course_id')) {
            return StudentResource::collection($query->paginate($this->perPage($request, 50)));
        }

        $course = $group->courses()->findOrFail($request->integer('course_id'));
        Gate::authorize('teach', $course);

        $students = $query->get();
        $sequence = $this->queue->currentSequence($course);
        $statuses = $this->queue->forStudents($course, $sequence, $students->modelKeys());
        $students->each(fn (Student $student) => $student->setRelation('queueStatus', $statuses->get($student->id)));

        $counts = collect([QueueStatus::NEVER_ASKED, QueueStatus::QUEUE, QueueStatus::DEBTOR, QueueStatus::RESTING])
            ->mapWithKeys(fn (string $state): array => [$state => $statuses->where('state', $state)->count()]);

        if ($request->filled('queue_state')) {
            $students = $students->filter(fn (Student $student): bool => $student->queueStatus->state === $request->input('queue_state'))->values();
        }

        return StudentResource::collection($students)->additional([
            'meta' => ['course_id' => $course->id, 'sequence' => $sequence, 'queue_counts' => $counts],
        ]);
    }

    public function store(SaveStudentRequest $request, StudyGroup $group): JsonResponse
    {
        $student = $group->students()->create($request->validated());

        return StudentResource::make($student)->response()->setStatusCode(201);
    }

    public function show(Student $student): StudentResource
    {
        Gate::authorize('view', $student);

        return StudentResource::make($student);
    }

    public function update(SaveStudentRequest $request, Student $student): StudentResource
    {
        $student->update($request->validated());

        return StudentResource::make($student);
    }

    /**
     * A student with grades can only be deactivated (is_active = false).
     */
    public function destroy(Student $student): Response
    {
        Gate::authorize('delete', $student);

        if ($student->answers()->exists()) {
            throw ResourceInUseException::hasGrades('Talaba', 'Uni nofaol qiling.');
        }

        $student->delete();

        return response()->noContent();
    }
}
