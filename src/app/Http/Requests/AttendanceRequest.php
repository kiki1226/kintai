<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
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
            'clock_in'   => ['nullable', 'date_format:H:i'],
            'clock_out'  => ['nullable', 'date_format:H:i', 'after:clock_in'],

            'breaks' => ['array'],
            'breaks.*.start' => ['nullable', 'date_format:H:i', 'after_or_equal:clock_in'],
            
            'breaks.*.end'   => ['nullable', 'date_format:H:i', 'after:clock_in', 'before_or_equal:clock_out', 'required_with:breaks.*.start'],

            'note' => ['required', 'string', 'max:200'],
        ];
    }

    public function messages(): array
    {
        return [
            'clock_out.after' => '出勤時間もしくは退勤時間が不適切な値です。',

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
            'clock_in'       => '出勤時刻',
            'clock_out'      => '退勤時刻',
            'breaks.*.start' => '休憩開始時刻',
            'breaks.*.end'   => '休憩終了時刻',
            'note'           => '備考',
        ];
    }
}
