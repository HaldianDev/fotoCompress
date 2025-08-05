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
            $request->validate([
                'image' => 'required|image|mimes:jpeg,jpg,png,JPG',
            ]);

            $image = $request->file('image');

            Log::info('Upload file detail:', [
                'originalName' => $image->getClientOriginalName(),
                'mimeType' => $image->getMimeType(),
                'size' => $image->getSize(),
                'isValid' => $image->isValid(),
            ]);

            if (!$image->isValid()) {
                return response()->json(['error' => 'File tidak valid.'], 400);
            }


            try {
                $img = Image::make($image->getRealPath())->orientate();
            } catch (\Exception $e) {
                Log::error('Image::make() gagal: ' . $e->getMessage());
                return response()->json([
                    'error' => 'Gagal membaca gambar.',
                    'message' => $e->getMessage()
                ], 400);
            }

            $quality = 30;
            $maxFileSize = 102400; 
            $maxWidth = 1024;

            // Resize awal
            $img->resize($maxWidth, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

            // Encode pertama
            $compressedImage = $img->encode('webp', $quality);

            // Loop untuk compress sampai ukuran < 100KB atau kualitas <= 30
            while (strlen($compressedImage->__toString()) > $maxFileSize && $quality > 20) {
                $quality -= 10;
                // Perlu encode ulang, pakai clone agar tidak merusak objek asli
                $compressedImage = (clone $img)->encode('webp', $quality);
            }

            // Cek apakah ukuran sudah sesuai
            $fileName = uniqid('siaptuba/foto_') . '.webp';
            $path = 'siaptuba/foto' . $fileName;

            Storage::disk('s3')->put($path, $compressedImage->__toString());

            return response()->json([
                'message' => 'Gambar berhasil dikompres dan diupload.',
                'path' => $path,
                'url' => Storage::disk('s3')->url($path),
            ]);
        } catch (\Exception $e) {
            Log::error('Compress Image Error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengompres gambar.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
