<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // الـ auth middleware هيتولى
    }

    public function rules(): array
    {
        return [
            'rating'   => ['required', 'integer', 'min:1', 'max:5'],
            'body'     => ['nullable', 'string', 'max:2000'],
            'images'   => ['nullable', 'array', 'max:3'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5MB
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => 'التقييم مطلوب',
            'rating.min'      => 'التقييم من 1 لـ 5',
            'rating.max'      => 'التقييم من 1 لـ 5',
            'images.max'      => 'أقصى عدد صور 3',
            'images.*.image'  => 'الملف لازم يكون صورة',
            'images.*.max'    => 'حجم الصورة أقصى 5 ميجا',
        ];
    }
}