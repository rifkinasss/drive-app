<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array{user: User, invitation: array, counts: array} $resource */
class AdminUserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $this->resource['user'];
        $result = (new AdminUserResource($user))->resolve($request);
        $result['invitation'] = $this->resource['invitation'];
        $result['counts'] = $this->resource['counts'];

        return $result;
    }
}
