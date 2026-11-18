<?php

namespace App\Http\Requests\Owner;

use Illuminate\Foundation\Http\FormRequest;

class MenuVideoRequest extends FormRequest
{
    public const VIDEO_MAX_KB = 20480;

    public function authorize(): bool
    {
        return $this->user()?->isOwner() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('video') === null;

        return [
            'title' => ['required', 'string', 'max:100'],
            'caption' => ['nullable', 'string', 'max:255'],
            // MP4 only: it plays in every mobile browser.
            'video' => [$creating ? 'required' : 'nullable', 'file', 'mimetypes:video/mp4', 'max:'.self::VIDEO_MAX_KB],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'judul',
            'caption' => 'keterangan',
            'video' => 'video',
            'sort_order' => 'urutan',
        ];
    }

    public function messages(): array
    {
        return [
            'video.mimetypes' => 'Video harus berformat MP4.',
            'video.max' => 'Ukuran video maksimal '.(self::VIDEO_MAX_KB / 1024).' MB.',
            'video.uploaded' => 'Video gagal diunggah. Biasanya karena melebihi batas upload server (upload_max_filesize di php.ini).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function videoData(): array
    {
        $data = $this->safe()->except('video');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
