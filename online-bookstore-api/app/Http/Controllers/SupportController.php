<?php

namespace App\Http\Controllers;

use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Models\SupportMessage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Help-centre FAQs, the in-app support form and image uploads.
 */
class SupportController extends Controller
{
    /**
     * The published FAQs, in display order.
     */
    public function faqs(Request $request): JsonResponse
    {
        $query = Faq::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id');

        $perPage = min((int) $request->input('per_page', 100), 100);
        $faqs = $query->paginate($perPage);

        return ApiResponse::paginated(
            $faqs,
            FaqResource::collection($faqs->items())->resolve(),
        );
    }

    /**
     * Store a message sent through the in-app support form.
     *
     * The client reads `data.id` as the confirmation id.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = SupportMessage::create([
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'message' => $validated['message'],
        ]);

        return ApiResponse::success(
            [
                'id' => (string) $message->id,
                'subject' => $message->subject,
                'message' => $message->message,
            ],
            'Your message has been sent.',
            status: 201,
        );
    }

    /**
     * Upload an image (e.g. a book cover or the user's avatar).
     *
     * Returns the public URL of the stored file under `url` — the key the
     * client reads.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120'],
        ]);

        $file = $request->file('file');
        $path = $file->store('uploads', 'public');

        return ApiResponse::success([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
            'name' => Str::basename($path),
        ]);
    }
}
