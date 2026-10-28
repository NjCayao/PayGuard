<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\VerificationIndexRequest;
use App\Http\Resources\Panel\VerificationResource;
use App\Models\Verification;
use App\Services\Panel\VerificationListQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VerificationController extends Controller
{
    public function index(VerificationIndexRequest $request, VerificationListQuery $list): AnonymousResourceCollection
    {
        $query = $list->for($request->user()->business, $request->validated())
            ->with('cashier');

        return VerificationResource::collection(
            $query->paginate(config('payguard.verifications_per_page'))
        );
    }

    public function show(int $verification): VerificationResource
    {
        $model = Verification::forBusiness(auth()->user()->business_id)
            ->listable()
            ->with(['cashier', 'receivedPayment', 'alerts'])
            ->findOrFail($verification);

        return new VerificationResource($model);
    }

    public function image(int $verification): BinaryFileResponse
    {
        return $this->media($verification, 'image');
    }

    public function audio(int $verification): BinaryFileResponse
    {
        return $this->media($verification, 'audio');
    }

    private function media(int $verification, string $inputType): BinaryFileResponse
    {
        $model = Verification::forBusiness(auth()->user()->business_id)
            ->findOrFail($verification);

        abort_if($model->input_type !== $inputType || $model->media_path === null, 404);

        $disk = Storage::disk(config('payguard.whatsapp.media_disk'));

        abort_if(! $disk->exists($model->media_path), 404);

        return response()->file($disk->path($model->media_path));
    }
}
