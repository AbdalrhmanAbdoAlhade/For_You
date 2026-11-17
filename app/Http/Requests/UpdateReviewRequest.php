<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rating'             => ['sometimes', 'integer', 'min:1', 'max:5'],
            'body'               => ['nullable', 'string', 'max:2000'],
            'images'             => ['nullable', 'array', 'max:3'],
            'images.*'           => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_image_ids'   => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.min'     => 'التقييم من 1 لـ 5',
            'rating.max'     => 'التقييم من 1 لـ 5',
            'images.max'     => 'أقصى عدد صور 3',
            'images.*.image' => 'الملف لازم يكون صورة',
        ];
    }
}