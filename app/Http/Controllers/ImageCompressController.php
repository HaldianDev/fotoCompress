<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ImageCompressController extends Controller
{
    public function compress(Request $request)
    {
        try {
            // Validasi input
            $validated = $request->validate([
                'image' => 'required|image|mimes:jpeg,jpg,png,JPG',
                'quality' => 'required|integer|min:1|max:100', 
                'direktori' => 'required|string', 
                'fileName' => 'required|string',
            ]);

            $image = $request->file('image');

            if (!$image->isValid()) {
                return response()->json([
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'File tidak valid.',
                    'errors' => [
                        ['field' => 'image', 'message' => 'File tidak valid.']
                    ]
                ], 400);
            }

            try {
                $img = Image::make($image->getRealPath())->orientate();
            } catch (\Exception $e) {
                return response()->json([
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Gagal membaca gambar.',
                    'errors' => [
                        ['field' => 'image', 'message' => $e->getMessage()]
                    ]
                ], 400);
            }

            $quality = $validated['quality'];
            $directory = $validated['direktori'];
            $fileName = $validated['fileName'];

            $compressedImage = $img->encode('webp', $quality);
            $path = $directory . '/' . $fileName;

            Storage::disk('s3')->put($path, $compressedImage->__toString());

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Gambar berhasil dikompres dan diupload.',
                'data' => [
                    'fileName' => $fileName,
                    'url' => Storage::disk('s3')->url($path),
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Tangani validasi Laravel
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $errors[] = ['field' => $field, 'message' => $message];
                }
            }

            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Validasi gagal.',
                'errors' => $errors
            ], 422);

        } catch (\Exception $e) {
            // Error umum
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'Terjadi kesalahan saat mengompres gambar.',
                'errors' => [
                    ['field' => 'server', 'message' => $e->getMessage()]
                ]
            ], 500);
        }
    }
}
