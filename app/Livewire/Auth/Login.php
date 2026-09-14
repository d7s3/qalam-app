<?php

namespace App\Livewire\Auth;

use App\Models\Guardian;
use App\Models\Manager;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\Teacher;
use App\Support\RoleHierarchy;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    /**
     * The model each guard's credentials live in — checked in order to find
     * which one owns the submitted email, so we only ever call attempt()
     * once instead of once per guard. Laravel pads every attempt() call to
     * a fixed 200ms floor to defeat login timing attacks; trying all 5
     * guards in sequence used to multiply that into up to a full second.
     */
    protected const GUARD_MODELS = [
        'student' => Student::class,
        'teacher' => Teacher::class,
        'guardian' => Guardian::class,
        'supervisor' => Supervisor::class,
        'manager' => Manager::class,
        'staff' => Staff::class,
    ];

    /**
     * URL prefix each guard's protected pages live under — used to decide
     * whether a stored "intended" URL actually belongs to the role that just
     * logged in, before honoring it instead of the plain dashboard redirect.
     */
    protected const GUARD_PREFIXES = [
        'student' => '/student',
        'teacher' => '/teacher',
        'guardian' => '/parent',
        'supervisor' => '/supervisor',
        'manager' => '/manager',
        'staff' => '/staff',
    ];

    public function login()
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Wrapped in our own timebox so an email that matches no guard at
        // all still takes the same ~200ms as a real account with a wrong
        // password — otherwise the response time itself would leak whether
        // an email is registered.
        $guard = (new Timebox)->call(function ($timebox) {
            $guard = $this->resolveGuardForEmail($this->email);

            if ($guard && Auth::guard($guard)->attempt([
                'email' => $this->email,
                'password' => $this->password,
            ], $this->remember)) {
                $timebox->returnEarly();

                return $guard;
            }

            return null;
        }, 200_000);

        if ($guard) {
            session()->regenerate();

            if ($intended = $this->intendedUrlFor($guard)) {
                return redirect()->to($intended);
            }

            return redirect()->route("{$guard}.dashboard");
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }

    /**
     * The offices a person holds, ranked when neither carries the other.
     *
     * Only a tie-break: the hierarchy decides between the three that carry one
     * another, and this settles the rest — a person who is both a manager and
     * somebody's guardian works here and is a parent after hours.
     */
    protected const SENIORITY = ['manager', 'supervisor', 'staff', 'teacher', 'guardian', 'student'];

    /**
     * Cheap indexed lookup (no password hashing involved) to find which
     * guard's table the email belongs to, if any.
     *
     * All of them are asked, not the first that answers. One person may hold
     * several offices — the offices share a table and differ by a role row —
     * and the loop used to stop at whichever came first in a list ordered for
     * speed rather than for rank. A centre manager who also teaches a cohort
     * was landed at the teacher's desk every morning and had to switch roles
     * to reach his own.
     */
    protected function resolveGuardForEmail(string $email): ?string
    {
        $held = [];

        foreach (self::GUARD_MODELS as $guard => $model) {
            if ($model::where('email', $email)->exists()) {
                $held[] = $guard;
            }
        }

        return $this->seniorOf($held);
    }

    /**
     * The most senior of the offices held, which is the desk to open at.
     *
     * Asked of RoleHierarchy rather than hardcoded, because the academy can
     * re-arrange who carries whom from its own settings screen, and the answer
     * has to follow it.
     *
     * @param  array<int, string>  $held
     */
    protected function seniorOf(array $held): ?string
    {
        usort($held, function (string $a, string $b) {
            $carries = count(RoleHierarchy::inheritedBy($b)) <=> count(RoleHierarchy::inheritedBy($a));

            return $carries !== 0
                ? $carries
                : array_search($a, self::SENIORITY, true) <=> array_search($b, self::SENIORITY, true);
        });

        return $held[0] ?? null;
    }

    /**
     * Only honor a stored intended URL when it points somewhere under the
     * authenticated guard's own section and isn't a login page itself —
     * otherwise a stale or cross-role intended URL could bounce the user
     * straight back to a login screen or into a page they can't access.
     */
    protected function intendedUrlFor(string $guard): ?string
    {
        $intended = session()->pull('url.intended');

        if (! $intended) {
            return null;
        }

        $path = parse_url($intended, PHP_URL_PATH) ?? '';
        $prefix = self::GUARD_PREFIXES[$guard];

        if (! str_starts_with($path, $prefix.'/') || str_ends_with($path, '/login')) {
            return null;
        }

        return $intended;
    }

    public function render()
    {
        return view('livewire.auth.login-unified');
    }
}
