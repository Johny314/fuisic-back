<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PermissionName;
use App\Enums\TeacherVerificationStatus as Status;
use App\Exceptions\TeacherVerificationConflict;
use App\Http\Controllers\Admin\Concerns\AuthorizesCrud;
use App\Models\TeacherVerification;
use App\Services\TeacherVerificationService;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Prologue\Alerts\Facades\Alert;

/**
 * Очередь заявок «Проверенный учитель»: просмотр, одобрение, отказ, отзыв статуса.
 */
class TeacherVerificationCrudController extends CrudController
{
    use AuthorizesCrud;
    use ListOperation;
    use ShowOperation;

    public const string ALL_STATUSES = 'all';

    public function setup()
    {
        CRUD::setModel(TeacherVerification::class);
        CRUD::setRoute(config('backpack.base.route_prefix').'/teacher-verification');
        CRUD::setEntityNameStrings('заявка учителя', 'Заявки учителей');

        $this->authorizeCrud(PermissionName::teachersVerify, ['decision']);
    }

    protected function setupDecisionRoutes($segment, $routeName, $controller)
    {
        foreach (['approve', 'reject', 'revoke'] as $action) {
            Route::post($segment.'/{id}/'.$action, [
                'as' => $routeName.'.'.$action,
                'uses' => $controller.'@'.$action,
                'operation' => 'decision',
            ]);
        }
    }

    /** Фильтр по статусу из ?status=…; по умолчанию — заявки на рассмотрении. */
    public static function statusFilter(): ?Status
    {
        $value = request()->query('status', Status::pending->value);

        return $value === self::ALL_STATUSES ? null : (Status::tryFrom((string) $value) ?? Status::pending);
    }

    protected function setupListOperation()
    {
        if ($status = self::statusFilter()) {
            CRUD::addClause('where', 'status', $status->value);
        }
        CRUD::addClause('with', 'user');
        CRUD::orderBy('id');
        CRUD::addButton('top', 'status_filter', 'view', 'admin.teacher-verification.status-filter');

        $this->addUserColumn();
        CRUD::column('full_name')->label('ФИО');
        $this->addWorkplaceColumn();
        $this->addSubjectsColumn();
        $this->addStatusColumn();
        CRUD::column('created_at')->label('Подана')->type('datetime');
        CRUD::column('reviewed_at')->label('Решение')->type('datetime');
    }

    protected function setupShowOperation()
    {
        CRUD::setShowView('admin.teacher-verification.show');

        $this->addUserColumn();
        CRUD::column('full_name')->label('ФИО');
        $this->addWorkplaceColumn();
        $this->addSubjectsColumn();
        CRUD::column('link')->label('Ссылка')->type('url');
        CRUD::column('comment')->label('Комментарий учителя')->type('textarea');
        $this->addStatusColumn();
        CRUD::column('created_at')->label('Подана')->type('datetime');
        CRUD::addColumn([
            'name' => 'reviewer',
            'label' => 'Проверяющий',
            'type' => 'closure',
            'function' => fn (TeacherVerification $entry) => $entry->reviewer?->name ?? '—',
        ]);
        CRUD::column('reviewed_at')->label('Дата решения')->type('datetime');
        CRUD::column('reviewer_comment')->label('Комментарий проверяющего (виден учителю)')->type('textarea');
    }

    public function approve($id): RedirectResponse
    {
        $this->crud->hasAccessOrFail('decision');
        $data = request()->validate(['reviewer_comment' => ['nullable', 'string', 'max:2000']]);

        return $this->decide($id, 'Заявка одобрена, учитель получил право публикации',
            fn (TeacherVerificationService $service, TeacherVerification $entry) => $service->approve($entry, backpack_user(), $data['reviewer_comment'] ?? null));
    }

    public function reject($id): RedirectResponse
    {
        $this->crud->hasAccessOrFail('decision');
        $data = request()->validate(['reviewer_comment' => ['required', 'string', 'max:2000']]);

        return $this->decide($id, 'Заявка отклонена',
            fn (TeacherVerificationService $service, TeacherVerification $entry) => $service->reject($entry, backpack_user(), $data['reviewer_comment']));
    }

    public function revoke($id): RedirectResponse
    {
        $this->crud->hasAccessOrFail('decision');
        $data = request()->validate(['reviewer_comment' => ['required', 'string', 'max:2000']]);

        return $this->decide($id, 'Статус «Проверенный учитель» отозван',
            fn (TeacherVerificationService $service, TeacherVerification $entry) => $service->revoke($entry, backpack_user(), $data['reviewer_comment']));
    }

    private function decide($id, string $success, Closure $action): RedirectResponse
    {
        $entry = TeacherVerification::query()->findOrFail($id);

        try {
            $action(app(TeacherVerificationService::class), $entry);
            Alert::success($success)->flash();
        } catch (TeacherVerificationConflict $e) {
            Alert::error($e->getMessage())->flash();
        }

        return redirect(url($this->crud->route.'/'.$entry->getKey().'/show'));
    }

    private function addUserColumn(): void
    {
        CRUD::addColumn([
            'name' => 'user',
            'label' => 'Пользователь',
            'type' => 'closure',
            'function' => fn (TeacherVerification $entry) => $entry->user
                ? $entry->user->name.' ('.$entry->user->email.')'
                : '—',
            'searchLogic' => fn ($query, $column, $term) => $query->orWhereHas('user', fn ($q) => $q
                ->where('name', 'ilike', '%'.$term.'%')
                ->orWhere('email', 'ilike', '%'.$term.'%')),
        ]);
    }

    private function addWorkplaceColumn(): void
    {
        CRUD::addColumn([
            'name' => 'workplace_name',
            'label' => 'Место работы',
            'type' => 'closure',
            'function' => fn (TeacherVerification $entry) => $entry->workplace_type->value.': '.$entry->workplace_name,
        ]);
    }

    private function addSubjectsColumn(): void
    {
        CRUD::addColumn([
            'name' => 'subjects',
            'label' => 'Предметы',
            'type' => 'closure',
            'function' => fn (TeacherVerification $entry) => implode(', ', $entry->subjects ?? []),
            'searchLogic' => false,
        ]);
    }

    private function addStatusColumn(): void
    {
        CRUD::addColumn([
            'name' => 'status',
            'label' => 'Статус',
            'type' => 'closure',
            'function' => fn (TeacherVerification $entry) => $entry->status->label(),
            'searchLogic' => false,
        ]);
    }
}
