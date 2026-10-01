<?php

namespace App\Models\User;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Branch\Branch;
use App\Models\Firm\Firm;
use App\Models\Salary\SalaryPayment;
use App\Models\User\UserTopUp;
use App\Models\Worker\Worker;
use App\Models\Worker\WorkerHoliday;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'google_id',
        'telegram_id',
        'avatar',
    ];

    protected $with = [
        'roles'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function worker_holidays()
    {
        return $this->hasMany(WorkerHoliday::class, 'user_id');
    }

    public function user_firms()
    {
        return $this->hasMany(UserFirm::class, 'user_id');
    }

    public function user_top_up()
    {
        return $this->hasMany(UserTopUp::class, 'user_id');
    }

    public function user_top_up_client()
    {
        return $this->hasMany(UserTopUp::class, 'client_id');
    }

    public function user_top_up_cleint()
    {
        return $this->user_top_up_client();
    }

    public function salary_payment()
    {
        return $this->hasMany(SalaryPayment::class, 'user_id');
    }

    /**
     * Check if user has access to a specific firm.
     */
    public function hasFirmAccess(int|Firm $firm): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        $firmId = $firm instanceof Firm ? $firm->id : (int) $firm;
        return $this->user_firms()->where('firm_id', $firmId)->exists();
    }

    /**
     * Check if user has access to a specific branch.
     */
    public function hasBranchAccess(int|Branch $branch): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        $branchModel = $branch instanceof Branch ? $branch : Branch::find($branch);
        if (!$branchModel) {
            return false;
        }

        return $this->hasFirmAccess($branchModel->firm_id);
    }

    /**
     * Check if user has access to a specific worker.
     */
    public function hasWorkerAccess(int|Worker $worker): bool
    {
        if ($this->hasRole('Admin')) {
            return true;
        }

        $workerModel = $worker instanceof Worker ? $worker : Worker::with('branch')->find($worker);
        if (!$workerModel || !$workerModel->branch) {
            return false;
        }

        return $this->hasFirmAccess($workerModel->branch->firm_id);
    }
}

