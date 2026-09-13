<?php

namespace App\Livewire\Supervisor;

use App\Models\Form;
use App\Models\User;
use App\Support\Scope;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Component;

/**
 * The forms a person may see and manage.
 *
 * Forms began as a supervisor's alone and are now written by managers and
 * teachers too, so ownership is read from the created_by pair rather than from
 * one guard — with supervisor_id still honoured for everything made before the
 * morph existed.
 */
class ManageForms extends Component
{
    /** The three offices that open this screen, narrowest first. */
    private const OFFICES = ['teacher', 'supervisor', 'manager'];

    /** The form whose public link is open in the panel, and the three things that decide it. */
    public ?int $sharingId = null;

    public bool $isPublic = false;

    public string $closesOn = '';

    public string $publicIntro = '';

    /**
     * The signed-in author and the role they are acting in.
     *
     * Read from the route, the way reach is read everywhere else: this one
     * screen hangs on three routes, and a person who holds two of these offices
     * at once must be answered for by the door he came through — not by
     * whichever guard happens to be checked first.
     *
     * @return array{0: User, 1: string}
     */
    private function author(): array
    {
        $role = Scope::resolveRole();

        if (in_array($role, self::OFFICES, true) && ($user = auth()->guard($role)->user())) {
            return [$user, $role];
        }

        // Otherwise whoever is signed in, narrowest office first — the same
        // order Scope falls back in, so an ambiguity resolves towards seeing
        // less rather than more.
        foreach (self::OFFICES as $guard) {
            if ($user = auth()->guard($guard)->user()) {
                return [$user, $guard];
            }
        }

        abort(403);
    }

    /**
     * What this author is allowed to open: their own forms, plus — for
     * supervisors — the ones shared across supervisors, and everything for a
     * manager, who answers for the academy.
     *
     * @return Builder<Form>
     */
    private function visibleForms()
    {
        [$author, $role] = $this->author();

        $query = Form::query();

        if ($role === 'manager') {
            return $query;
        }

        return $query->where(function ($q) use ($author, $role) {
            $q->where(fn ($own) => $own->where('created_by_id', $author->id)->where('created_by_type', $role));

            if ($role === 'supervisor') {
                // Legacy ownership, from before forms could belong to anyone else.
                $q->orWhere('supervisor_id', $author->id)
                    ->orWhere('is_supervisor_shared', true);
            }
        });
    }

    /**
     * Whether this author may change the form itself, not merely open it.
     *
     * Viewing is wider than changing: a form shared across supervisors appears
     * on everyone's screen, but only its author — or a manager, who answers for
     * the academy — may delete it or hand its link to the public.
     */
    private function mayChange(Form $form): bool
    {
        [$author, $role] = $this->author();

        return ($form->created_by_id === $author->id && $form->created_by_type === $role)
            || ($role === 'supervisor' && $form->supervisor_id === $author->id)
            || $role === 'manager';
    }

    /** The form behind an id, refused unless this author may change it. */
    private function changeable(?int $id): Form
    {
        $form = $this->visibleForms()->findOrFail($id);

        abort_unless($this->mayChange($form), 403);

        return $form;
    }

    public function delete($id): void
    {
        $this->changeable($id)->delete();

        Flux::toast('تم حذف النموذج بنجاح', variant: 'success');
    }

    /** Open the panel that decides whether strangers may answer this form. */
    public function share(int $id): void
    {
        $form = $this->changeable($id);

        $this->sharingId = $form->id;
        $this->isPublic = (bool) $form->is_public;
        $this->closesOn = $form->closes_on?->toDateString() ?? '';
        $this->publicIntro = $form->public_intro ?? '';

        $this->resetValidation();

        Flux::modal('public-link')->show();
    }

    public function saveSharing(): void
    {
        $this->validate([
            'closesOn' => ['nullable', 'date'],
            'publicIntro' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'closesOn' => 'تاريخ الإغلاق',
            'publicIntro' => 'التعريف',
        ]);

        $form = $this->changeable($this->sharingId);

        // The token is minted once and then kept, even while the form is shut,
        // so a link already in somebody's hands still works if it is reopened.
        if ($this->isPublic && ! $form->public_token) {
            $form->public_token = Str::random(24);
        }

        $form->fill([
            'is_public' => $this->isPublic,
            'closes_on' => $this->closesOn ?: null,
            'public_intro' => $this->publicIntro ?: null,
        ])->save();

        Flux::toast(
            $this->isPublic ? 'النموذج مفتوحٌ للعامة' : 'أُغلق النموذج عن العامة',
            variant: 'success',
        );
    }

    public function render()
    {
        [$author, $role] = $this->author();

        $forms = $this->visibleForms()
            ->withCount(['responses', 'assignments'])
            ->with('supervisor:id,name')
            ->latest()
            ->get();

        return view('livewire.supervisor.manage-forms', [
            'forms' => $forms,
            'currentSupervisorId' => $author->id,
            'currentRole' => $role,
            'sharing' => $this->sharingId ? Form::find($this->sharingId) : null,
            // Worked out here rather than asked per card, so the view holds a
            // list of ids and the guards are never a callable surface.
            'changeableIds' => $forms->filter(fn (Form $form) => $this->mayChange($form))->pluck('id')->all(),
        ]);
    }
}
