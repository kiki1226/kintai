<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AttendanceDayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'clock_in_at'  => ['nullable', 'date_format:H:i'],
            'clock_out_at' => ['nullable', 'date_format:H:i', 'after:clock_in_at'],

            'breaks'         => ['array'],
            'breaks.*.start' => ['nullable', 'date_format:H:i', 'after_or_equal:clock_in_at'],
            'breaks.*.end'   => ['nullable', 'date_format:H:i', 'after:breaks.*.start', 'before_or_equal:clock_out_at', 'required_with:breaks.*.start'],

            'note' => ['required', 'string', 'max:200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        // 退勤時刻が入力されている場合のみ「休憩終了 <= 退勤」を適用
        if ($this->filled('clock_out_at')) {
            $validator->addRules([
                'breaks.*.end' => ['before_or_equal:clock_out_at'],
            ]);
        }
    }

    public function messages(): array
    {
        return [
            // 出退勤
            'clock_out_at.after'        => '出勤時間もしくは退勤時間が不適切な値です。',
            
            // 休憩
            'breaks.*.start.after_or_equal' => '休憩時間が勤務時間外です。',
            'breaks.*.end.required_with'    => '休憩時間が勤務時間外です。',
            'breaks.*.end.after'            => '休憩時間が勤務時間外です。',
            'breaks.*.end.before_or_equal'  => '休憩時間もしくは退勤時間が不適切な値です。',
    

            'note.required' => '備考を記入してください。',
            'note.max'      => '備考は200文字以内で入力してください。',
        ];
    }

    public function attributes(): array
    {
        return [
            'clock_in_at'    => '出勤時刻',
            'clock_out_at'   => '退勤時刻',
            'breaks.*.start' => '休憩開始時刻',
            'breaks.*.end'   => '休憩終了時刻',
            'note'           => '備考',
        ];
    }
}
