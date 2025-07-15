<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class TestController extends Controller
{
    /**
     * Test endpoint to verify UTF-8 handling
     */
    public function testUtf8()
    {
        return response()->json([
            'message' => 'UTF-8 test successful',
            'data' => [
                'special_chars' => 'éñüíóáàèùïôâêîôûç',
                'binary_safe' => true,
                'timestamp' => now()->toISOString(),
            ]
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Test endpoint to verify binary data handling
     */
    public function testBinary()
    {
        try {
            // Create a simple test image (1x1 pixel PNG)
            $testImageData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

            // Test base64 encoding
            $base64Data = base64_encode($testImageData);

            return response()->json([
                'message' => 'Binary test successful',
                'data' => [
                    'image_size' => strlen($testImageData),
                    'base64_size' => strlen($base64Data),
                    'mime_type' => 'image/png',
                    'data_uri' => 'data:image/png;base64,' . $base64Data,
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Binary test failed: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Test endpoint to check memory usage
     */
    public function testMemory()
    {
        try {
            $memoryBefore = memory_get_usage(true);
            $peakMemoryBefore = memory_get_peak_usage(true);

            return response()->json([
                'message' => 'Memory test successful',
                'data' => [
                    'memory_usage' => $memoryBefore,
                    'memory_usage_formatted' => $this->formatBytes($memoryBefore),
                    'peak_memory' => $peakMemoryBefore,
                    'peak_memory_formatted' => $this->formatBytes($peakMemoryBefore),
                    'memory_limit' => ini_get('memory_limit'),
                    'available_memory' => $this->formatBytes($this->getAvailableMemory()),
                ]
            ], 200, [], JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Memory test failed: ' . $e->getMessage(),
                'data' => []
            ], 500, [], JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get available memory in bytes
     */
    private function getAvailableMemory()
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit == -1) {
            return PHP_INT_MAX;
        }

        $memoryLimit = $this->parseMemoryLimit($memoryLimit);
        $currentUsage = memory_get_usage(true);

        return $memoryLimit - $currentUsage;
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit($memoryLimit)
    {
        $memoryLimit = trim($memoryLimit);
        $last = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) $memoryLimit;

        switch ($last) {
            case 'g':
                $memoryLimit *= 1024;
            case 'm':
                $memoryLimit *= 1024;
            case 'k':
                $memoryLimit *= 1024;
        }

        return $memoryLimit;
    }
}
