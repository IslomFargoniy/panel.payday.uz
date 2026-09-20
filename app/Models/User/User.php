<?php

namespace App\Models\User;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Salary\SalaryPayment;
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
        return $this->hasMany(UserFirm::class, 'user_id');
    }

    public function user_top_up_cleint()
    {
        return $this->hasMany(UserFirm::class, 'client_id');
    }

    public function salary_payment()
    {
        return $this->hasMany(SalaryPayment::class, 'user_id');
    }


}
