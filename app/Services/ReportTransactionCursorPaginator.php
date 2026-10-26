<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;

class ReportTransactionCursorPaginator
{
    public function paginate(Builder $query, Request $request, int $perPage): ReportCursorPaginator
    {
        $cursor = $this->cursorFromRequest($request);
        $previous = $cursor?->pointsToPreviousItems() ?? false;

        if ($cursor !== null) {
            $this->applyCursorConstraint($query, $cursor, $previous);
        }

        $direction = $previous ? 'asc' : 'desc';
        $items = (clone $query)
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->limit($perPage)
            ->get();
        $hasMore = false;

        if ($items->count() === $perPage) {
            $lastItem = $items->last();
            $probe = clone $query;
            $this->applyCursorConstraint(
                $probe,
                new Cursor([
                    'created_at' => $lastItem->getRawOriginal('created_at'),
                    'id' => $lastItem->getKey(),
                ], $previous),
                $previous
            );
            $hasMore = $probe->exists();
        }

        $queryParameters = $request->query();
        unset($queryParameters['cursor']);

        $paginator = new ReportCursorPaginator($items, $perPage, $cursor, [
            'path' => $request->url(),
            'query' => $queryParameters,
            'cursorName' => 'cursor',
            'parameters' => ['created_at', 'id'],
        ]);
        $paginator->setHasMore($hasMore);

        return $paginator;
    }

    private function cursorFromRequest(Request $request): ?Cursor
    {
        $encodedCursor = $request->query('cursor');

        if ($encodedCursor === null) {
            return null;
        }

        if (
            ! is_string($encodedCursor)
            || strlen($encodedCursor) > 1024
            || preg_match('/^[A-Za-z0-9_-]+$/', $encodedCursor) !== 1
        ) {
            abort(422, 'The report cursor is invalid.');
        }

        $paddedCursor = strtr(
            str_pad($encodedCursor, (int) (ceil(strlen($encodedCursor) / 4) * 4), '='),
            '-_',
            '+/'
        );
        $decodedCursor = base64_decode($paddedCursor, true);
        $parameters = is_string($decodedCursor) ? json_decode($decodedCursor, true) : null;

        if (
            ! is_array($parameters)
            || array_is_list($parameters)
            || ! array_key_exists('created_at', $parameters)
            || ! array_key_exists('id', $parameters)
            || ! array_key_exists('_pointsToNextItems', $parameters)
            || ! is_bool($parameters['_pointsToNextItems'])
            || count($parameters) !== 3
        ) {
            abort(422, 'The report cursor is invalid.');
        }

        $pointsToNextItems = $parameters['_pointsToNextItems'];
        unset($parameters['_pointsToNextItems']);

        if (
            ! isset($parameters['id'])
            || ! is_scalar($parameters['id'])
            || preg_match('/^[1-9][0-9]*$/', (string) $parameters['id']) !== 1
        ) {
            abort(422, 'The report cursor is invalid.');
        }

        $createdAt = $parameters['created_at'];
        if (
            $createdAt !== null
            && (
                ! is_string($createdAt)
                || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/', $createdAt) !== 1
            )
        ) {
            abort(422, 'The report cursor is invalid.');
        }

        return new Cursor($parameters, $pointsToNextItems);
    }

    private function applyCursorConstraint(Builder $query, Cursor $cursor, bool $previous): void
    {
        $createdAt = $cursor->parameter('created_at');
        $id = $cursor->parameter('id');

        if (! $previous && $createdAt === null) {
            $query->whereNull('created_at')->where('id', '<', $id);

            return;
        }

        if ($previous && $createdAt === null) {
            $query->where(function (Builder $query) use ($id): void {
                $query->whereNotNull('created_at')
                    ->orWhere(function (Builder $undated) use ($id): void {
                        $undated->whereNull('created_at')->where('id', '>', $id);
                    });
            });

            return;
        }

        $query->where(function (Builder $query) use ($createdAt, $id, $previous): void {
            $query->where('created_at', $previous ? '>' : '<', $createdAt)
                ->orWhere(function (Builder $sameTimestamp) use ($createdAt, $id, $previous): void {
                    $sameTimestamp->where('created_at', '=', $createdAt)
                        ->where('id', $previous ? '>' : '<', $id);
                });

            if (! $previous) {
                $query->orWhereNull('created_at');
            }
        });
    }
}
