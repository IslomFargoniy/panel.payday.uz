<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSalaryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user && $this->worker_id && !$user->hasRole('Admin')) {
            return $user->hasWorkerAccess((int) $this->worker_id);
        }
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'comment' => $this->comment && trim($this->comment) !== '' ? trim($this->comment) : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'worker_id'      => 'required|exists:workers,id',
            'amount'         => 'required|numeric|gt:0',
            'worked_minute'  => 'required|numeric|gt:0',
            'break_minute'   => 'required|numeric|min:0',
            'hour_price'     => 'required|numeric|gt:0',
            'from'           => 'required|date',
            'to'             => 'required|date|after_or_equal:from',
            'comment'        => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $workerId = $this->worker_id;
            $from = $this->from;
            $to = $this->to;

            if ($workerId && $from && $to) {
                // 1. Prevent overlapping salary periods for the same worker
                $overlapExists = \App\Models\Salary\Salary::where('worker_id', $workerId)
                    ->where(function ($q) use ($from, $to) {
                        $q->where('from', '<=', $to)
                          ->where('to', '>=', $from);
                    })
                    ->exists();

                if ($overlapExists) {
                    $validator->errors()->add('from', 'Ushbu xodim uchun tanlangan davr bilan kesishuvchi maosh allaqachon mavjud.');
                }

                // 2. Server-side recalculation of worked_minute and fine deduction
                $worker = \App\Models\Worker\Worker::find($workerId);
                if ($worker) {
                    $pairingService = app(\App\Services\Attendance\AttendancePairingService::class);
                    $subReq = new \Illuminate\Http\Request([
                        'worker_id' => $workerId,
                        'from' => $from,
                        'to' => $to,
                    ]);
                    $pairedEvents = $pairingService->buildPairedEventsQuery($subReq, $from, $to)->get();
                    $serverWorkedMinutes = (int) $pairedEvents->sum('worked_minutes');
                    $serverLateMinutes = (int) $pairedEvents->sum('late_minutes');

                    $hasEventsInSystem = \App\Models\Hikvision\HikvisionAccessEvent::where('employeeNoString', $worker->employeeNoString)->exists();
                    $clientWorkedMinutes = (int) $this->worked_minute;

                    // Agar tizimda xodim eventlari mavjud bo'lsa yoki juftlangan eventlar bo'lsa,
                    // klient yuborgan worked_minute server hisoblagan qiymatdan 5 daqiqadan ko'proq farq qilsa rad etiladi.
                    // Izoh mavjudligi bu tekshiruvni chetlab o'tolmaydi.
                    if (($pairedEvents->isNotEmpty() || $hasEventsInSystem) && abs($clientWorkedMinutes - $serverWorkedMinutes) > 5) {
                        $validator->errors()->add(
                            'worked_minute',
                            'Ishlangan daqiqalar tanlangan davr bo\'yicha server hisoblagan qiymatdan (' . $serverWorkedMinutes . ' daqiqa) farq qiladi. Iltimos, hisobotni qayta hisoblang.'
                        );
                    }

                    $hourPrice = (float) ($this->hour_price ?: $worker->hour_price);
                    $finePrice = (float) ($worker->fine_price ?? 0);

                    $earned = ($serverWorkedMinutes * $hourPrice) / 60;
                    $penalty = ($serverLateMinutes / 60) * $finePrice;
                    $expectedAmount = (int) max(0, round($earned - $penalty));

                    // Fallback to client worked_minute only if no events in system (e.g. manual entry)
                    if ($expectedAmount === 0 && !$hasEventsInSystem && $clientWorkedMinutes > 0) {
                        $expectedAmount = (int) max(0, round(($clientWorkedMinutes * $hourPrice) / 60));
                    }

                    if ($expectedAmount > 0 && abs((int)$this->amount - $expectedAmount) > 100 && empty($this->comment)) {
                        $validator->errors()->add(
                            'comment',
                            'Maosh summasi hisoblangan miqdordan farq qiladi (' . number_format($expectedAmount, 0, '', ' ') . ' so\'m). Iltimos, izoh (comment) qoldiring.'
                        );
                    }
                }
            }
        });
    }

}
