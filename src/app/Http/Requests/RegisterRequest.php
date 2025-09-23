<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name'  => trim((string) $this->input('name')),
            'email' => trim((string) mb_convert_kana($this->input('email'), 'a')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'      => 'このメールアドレスは既に使用されています。',
            'email.required'    => 'メールアドレスを入力してください。',
            'email.email'       => 'メールアドレスを正しい形式で入力してください。',
            'name.required'     => 'お名前を入力してください。',
            'password.required' => 'パスワードを入力してください。',
            'password.min'      => 'パスワードは:min文字以上で入力してください。',
            'password.confirmed'=> 'パスワードと一致しません。',
        ];
    }

    public function attributes(): array
    {
        return [
            'name'                  => '名前',
            'email'                 => 'メールアドレス',
            'password'              => 'パスワード',
            'password_confirmation' => 'パスワード（確認）',
        ];
    }
}
