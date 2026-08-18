<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class Controller
{
    use AuthorizesRequests;

    protected function paginateCollection(iterable $items, Request $request, int $perPage = 12): LengthAwarePaginator
    {
        $collection = $items instanceof Collection ? $items->values() : collect($items)->values();
        $perPage = max(1, $perPage);
        $lastPage = max(1, (int) ceil($collection->count() / $perPage));
        $page = min(max(1, (int) $request->integer('page', 1)), $lastPage);

        return new LengthAwarePaginator(
            $collection->forPage($page, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->except(['page', 'partial']),
            ]
        );
    }
}
