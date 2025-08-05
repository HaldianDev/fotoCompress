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
            // Validasi gambar
            $request->validate([
                'image' => 'required|image|mimes:jpeg,jpg,png,JPG',
                'quality' => 'required|integer|min:1|max:100', 
                'direktori' => 'required|string', 
                'fileName' => 'required|string',
            ]);

            // Mengambil file image dari request
            $image = $request->file('image');

            // Cek validitas file
            if (!$image->isValid()) {
                return response()->json(['error' => 'File tidak valid.'], 400);
            }

            try {
                $img = Image::make($image->getRealPath())->orientate();
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Gagal membaca gambar.',
                    'message' => $e->getMessage()
                ], 400);
            }

            // Mendapatkan parameter dari request
            $quality = $request->input('quality'); 
            $directory = $request->input('direktori');
            $fileName = $request->input('fileName'); 

            // Encode gambar menjadi format webp dengan kualitas yang diinput
            $compressedImage = $img->encode('webp', $quality);

            // Menentukan path untuk penyimpanan di S3
            $path = $directory . '/' . $fileName;

            // Menyimpan gambar yang telah dikompres ke S3
            Storage::disk('s3')->put($path, $compressedImage->__toString());

            // Mengembalikan response JSON dengan informasi path dan URL gambar
            return response()->json([
                'message' => 'Gambar berhasil dikompres dan diupload.',
                'path' => $fileName,
                // 'url' => Storage::disk('s3')->url($path),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Terjadi kesalahan saat mengompres gambar.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
