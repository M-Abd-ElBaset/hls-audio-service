<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class StoreTrackRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'mimes:mp3,wav,flac',
                'max:204800', // 200MB in KB
            ],
            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
            'artist' => [
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->hasFile('file')) {
                $file = $this->file('file');
                
                // Validate audio duration (max 3 hours)
                $this->validateAudioDuration($validator, $file);
                
                // Validate MIME type more thoroughly
                $this->validateMimeType($validator, $file);
            }
        });
    }

    /**
     * Validate audio duration doesn't exceed 3 hours.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return void
     */
    protected function validateAudioDuration(Validator $validator, $file): void
    {
        try {
            // Use FFprobe to get audio duration
            $ffprobeCommand = [
                'ffprobe',
                '-v', 'error',
                '-show_entries', 'format=duration',
                '-of', 'default=noprint_wrappers=1:nokey=1',
                $file->getRealPath()
            ];
            
            $process = new \Symfony\Component\Process\Process($ffprobeCommand);
            $process->run();
            
            if ($process->isSuccessful()) {
                $duration = (float) trim($process->getOutput());
                
                // Convert to milliseconds and check if > 3 hours (10,800,000 ms)
                if (($duration * 1000) > 10800000) {
                    $validator->errors()->add(
                        'file', 
                        'The audio duration must not exceed 3 hours'
                    );
                }
            }
        } catch (\Exception $e) {
            // If we can't determine duration, we'll still allow the upload
            // but the transcode job will fail if it's too long
            Log::warning('Could not determine audio duration', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate MIME type more thoroughly.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return void
     */
    protected function validateMimeType(Validator $validator, $file): void
    {
        $allowedMimes = [
            'audio/mpeg',    // MP3
            'audio/mp3',     // MP3
            'audio/wav',     // WAV
            'audio/x-wav',   // WAV
            'audio/flac',    // FLAC
            'audio/x-flac',  // FLAC
        ];
        
        $mime = $file->getMimeType();
        
        if (!in_array($mime, $allowedMimes)) {
            $validator->errors()->add(
                'file', 
                'The file must be a valid audio file (MP3, WAV, or FLAC)'
            );
        }
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'An audio file is required',
            'file.file' => 'The uploaded file is not valid',
            'file.mimes' => 'The file must be an MP3, WAV, or FLAC audio file',
            'file.max' => 'The file may not be larger than 200MB',
            'title.max' => 'The title may not be longer than 255 characters',
            'artist.max' => 'The artist name may not be longer than 255 characters',
        ];
    }

    /**
     * Get data to be validated from the request.
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        $data = parent::validationData();
        
        // Set default title from filename if not provided
        if (empty($data['title']) && $this->hasFile('file')) {
            $filename = pathinfo($this->file('file')->getClientOriginalName(), PATHINFO_FILENAME);
            $data['title'] = $filename;
        }
        
        return $data;
    }
}
