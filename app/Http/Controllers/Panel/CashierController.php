<?php

namespace App\Http\Controllers\Panel;

use App\Exceptions\CannotDeleteCashier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreCashierRequest;
use App\Http\Requests\Panel\UpdateCashierRequest;
use App\Http\Resources\Panel\CashierResource;
use App\Models\Cashier;
use App\Services\Demo\DemoProtection;
use App\Services\Panel\CashierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashierController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $cashiers = Cashier::forBusiness($request->user()->business_id)
            ->orderBy('name')
            ->paginate(config('payguard.cashiers_per_page'));

        return CashierResource::collection($cashiers);
    }

    public function store(StoreCashierRequest $request): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $cashier = Cashier::create([
            'business_id' => $request->user()->business_id,
            ...$request->validated(),
        ]);

        return response()->json(['data' => new CashierResource($cashier)], 201);
    }

    public function update(UpdateCashierRequest $request, int $cashier): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $model = $this->findForBusiness($request, $cashier);
        $model->update($request->validated());

        return response()->json(['data' => new CashierResource($model)]);
    }

    public function activate(Request $request, int $cashier): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $model = $this->findForBusiness($request, $cashier);
        $model->update(['is_active' => true]);

        return response()->json(['data' => new CashierResource($model)]);
    }

    public function deactivate(Request $request, int $cashier): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $model = $this->findForBusiness($request, $cashier);
        $model->update(['is_active' => false]);

        return response()->json(['data' => new CashierResource($model)]);
    }

    public function destroy(Request $request, int $cashier, CashierService $service): JsonResponse
    {
        DemoProtection::ensureWritable($request->user()->business);

        $model = $this->findForBusiness($request, $cashier);

        try {
            $service->delete($model);
        } catch (CannotDeleteCashier $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => true]);
    }

    private function findForBusiness(Request $request, int $cashier): Cashier
    {
        return Cashier::forBusiness($request->user()->business_id)->findOrFail($cashier);
    }
}
