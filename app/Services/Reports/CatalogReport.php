<?php

namespace App\Services\Reports;

use App\Models\ActivationWalletTransaction;
use App\Models\LevelCommissionTransaction;
use App\Models\Member;
use App\Models\Package;
use App\Models\PackageLevelAccrual;
use App\Models\PackagePurchase;
use App\Models\PackageRoiAccrual;
use App\Models\RoiTransaction;
use App\Support\MoneyDisplay;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogReport
{
    public const PER_PAGE = 25;

    public const NOT_TRACKED = 'Not tracked';

    public const NOT_STORED = 'Not stored';

    /**
     * @return array<string, mixed>
     */
    public function filters(array $input, string $report): array
    {
        $statuses = match ($report) {
            'members' => ['active', 'inactive'],
            'packages' => ['active', 'inactive', 'pending', 'approved', 'rejected'],
            'payments' => [PackagePurchase::PENDING, PackagePurchase::APPROVED, PackagePurchase::REJECTED],
            default => ['generated'],
        };

        $validated = validator($input, [
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'member_id' => ['nullable', 'string', 'max:32'],
            'package' => ['nullable', 'string', 'max:64'],
            'currency' => ['nullable', 'string', Rule::in([MoneyDisplay::USD, MoneyDisplay::USDT])],
            'income_type' => ['nullable', 'string', Rule::in(['roi', 'level', 'wallet'])],
            'dataset' => ['nullable', 'string', Rule::in(['catalog', 'purchases'])],
            'sort' => ['nullable', 'string', Rule::in(['newest', 'oldest'])],
        ])->validate();

        if (! empty($validated['from']) && ! empty($validated['to']) && $validated['from'] > $validated['to']) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be on or after the start date.',
            ]);
        }

        $validated['sort'] = $validated['sort'] ?? 'newest';
        $validated['dataset'] = $report === 'packages' ? ($validated['dataset'] ?? 'purchases') : null;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function page(string $report, array $filters): array
    {
        $packages = Package::query()->orderBy('name')->get(['id', 'code', 'name', 'denomination']);

        return match ($report) {
            'members' => $this->memberPage($filters, $packages),
            'packages' => $this->packagePage($filters, $packages),
            'payments' => $this->paymentPage($filters, $packages),
            'earnings' => $this->earningPage($filters, $packages),
            default => abort(404),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return \Generator<int, array<string, string>>
     */
    public function exportRows(string $report, array $filters): \Generator
    {
        $query = $this->exportQuery($report, $filters);
        $page = 0;

        do {
            $rows = (clone $query)->forPage(++$page, 200)->get();
            foreach ($rows as $row) {
                yield $this->present($report, $row);
            }
        } while ($rows->count() === 200);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportCount(string $report, array $filters): int
    {
        return $this->exportQuery($report, $filters)->count();
    }

    /**
     * @return array<int, string>
     */
    public function headings(string $report, array $filters): array
    {
        if ($report === 'packages' && ($filters['dataset'] ?? 'purchases') === 'catalog') {
            return ['Package name', 'Package type', 'Price', 'Currency', 'Code', 'Catalog status', 'ROI eligible', 'Level income eligible'];
        }

        return match ($report) {
            'members' => ['Member ID', 'Full name', 'Email', 'Mobile', 'Sponsor ID', 'Sponsor name', 'Registered', 'Account status', 'Approved package', 'Last login', 'Activation date', 'Activated by'],
            'packages' => ['Package name', 'Package type', 'Price', 'Currency', 'Purchase ID', 'Member ID', 'Member name', 'Purchased', 'Payment status', 'Reviewed', 'Approved by'],
            'payments' => ['Payment reference', 'Member ID', 'Member name', 'Package', 'Amount', 'Currency', 'Network', 'Transaction ID', 'Submitted', 'Status', 'Reviewed', 'Reviewed by', 'Rejection reason'],
            default => ['Member ID', 'Member name', 'Income type', 'Source member', 'Source', 'Level', 'Amount', 'Currency', 'Business date', 'Credited', 'Reference', 'Status', 'Ledger'],
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, string>
     */
    public function describeFilters(array $filters): array
    {
        $labels = [
            'q' => 'Search',
            'from' => 'From',
            'to' => 'To',
            'status' => 'Status',
            'member_id' => 'Member ID',
            'package' => 'Package',
            'currency' => 'Currency',
            'income_type' => 'Income type',
            'dataset' => 'Dataset',
            'sort' => 'Sort',
        ];
        $described = [];
        foreach ($labels as $key => $label) {
            if (! empty($filters[$key])) {
                $described[$label] = (string) $filters[$key];
            }
        }

        return $described;
    }

    public function withoutLimits(array $filters): array
    {
        $dataset = $filters['dataset'] ?? null;
        $sort = $filters['sort'] ?? 'newest';

        return array_filter([
            'dataset' => $dataset,
            'sort' => $sort,
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  \Illuminate\Support\Collection<int, Package>  $packages
     * @return array<string, mixed>
     */
    private function memberPage(array $filters, $packages): array
    {
        $rows = $this->memberQuery($filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'rows' => $rows,
            'packages' => $packages,
            'totals' => [],
            'counts' => [
                'Records' => (string) $rows->total(),
                'With an approved package' => (string) $this->memberQuery($filters)->whereHas('packagePurchases', fn (Builder $query) => $query->where('status', PackagePurchase::APPROVED))->count(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  \Illuminate\Support\Collection<int, Package>  $packages
     * @return array<string, mixed>
     */
    private function packagePage(array $filters, $packages): array
    {
        $catalog = ($filters['dataset'] ?? 'purchases') === 'catalog';
        $rows = ($catalog ? $this->catalogQuery($filters) : $this->purchaseQuery($filters))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return [
            'rows' => $rows,
            'packages' => $packages,
            'totals' => $catalog ? $this->currencyTotals($this->catalogQuery($filters), 'price_usd') : $this->currencyTotals($this->purchaseQuery($filters), 'price_usd'),
            'counts' => ['Records' => (string) $rows->total()],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  \Illuminate\Support\Collection<int, Package>  $packages
     * @return array<string, mixed>
     */
    private function paymentPage(array $filters, $packages): array
    {
        $rows = $this->purchaseQuery($filters)->paginate(self::PER_PAGE)->withQueryString();

        return [
            'rows' => $rows,
            'packages' => $packages,
            'totals' => $this->currencyTotals($this->purchaseQuery($filters), 'price_usd'),
            'counts' => ['Records' => (string) $rows->total()],
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  \Illuminate\Support\Collection<int, Package>  $packages
     * @return array<string, mixed>
     */
    private function earningPage(array $filters, $packages): array
    {
        $query = $this->earningQuery($filters);
        $rows = $query->paginate(self::PER_PAGE)->withQueryString();

        return [
            'rows' => $rows,
            'packages' => $packages,
            'totals' => [],
            'counts' => [
                'Stored ROI records' => (string) (RoiTransaction::query()->count() + PackageRoiAccrual::query()->count()),
                'Stored level income' => (string) (LevelCommissionTransaction::query()->count() + PackageLevelAccrual::query()->count()),
                'Matching records' => (string) $rows->total(),
            ],
        ];
    }

    private function memberQuery(array $filters): Builder
    {
        $query = Member::query()->withExists([
            'packagePurchases as has_approved_package' => fn (Builder $purchase) => $purchase->where('status', PackagePurchase::APPROVED),
        ]);
        $this->search($query, $filters['q'] ?? null, ['member_id', 'member_name', 'email', 'mobile_no']);
        if (! empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        $this->between($query, 'created_at', $filters);
        if (! empty($filters['package'])) {
            $query->whereHas('packagePurchases', fn (Builder $purchase) => $this->packageConstraint($purchase, $filters['package']));
        }
        if (! empty($filters['currency'])) {
            $query->whereHas('packagePurchases', fn (Builder $purchase) => $purchase->where('denomination', $filters['currency']));
        }

        return $this->sort($query, 'created_at', $filters);
    }

    private function catalogQuery(array $filters): Builder
    {
        $query = Package::query();
        $this->search($query, $filters['q'] ?? null, ['name', 'code']);
        if (($filters['status'] ?? null) === 'active') {
            $query->where('is_active', true);
        } elseif (($filters['status'] ?? null) === 'inactive') {
            $query->where('is_active', false);
        }
        if (! empty($filters['currency'])) {
            $query->where('denomination', $filters['currency']);
        }
        if (! empty($filters['package'])) {
            $query->where(function (Builder $inner) use ($filters): void {
                $inner->where('code', $filters['package'])->orWhere('id', $filters['package']);
            });
        }
        $this->between($query, 'created_at', $filters);

        return $this->sort($query, 'created_at', $filters);
    }

    private function purchaseQuery(array $filters): Builder
    {
        $query = PackagePurchase::query()->with([
            'member:id,member_id,member_name',
            'reviewer:id,name',
        ]);
        $this->search($query, $filters['q'] ?? null, ['reference', 'member_id', 'package_name', 'payment_reference']);
        if (! empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }
        if (! empty($filters['status']) && in_array($filters['status'], [PackagePurchase::PENDING, PackagePurchase::APPROVED, PackagePurchase::REJECTED], true)) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['package'])) {
            $this->packageConstraint($query, $filters['package']);
        }
        if (! empty($filters['currency'])) {
            $query->where('denomination', $filters['currency']);
        }
        $this->between($query, 'submitted_at', $filters);

        return $this->sort($query, 'submitted_at', $filters);
    }

    private function earningQuery(array $filters): QueryBuilder
    {
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';
        $union = $this->earningUnion($filters);

        return DB::query()
            ->fromSub($union, 'earnings')
            ->orderBy('credited_at', $direction)
            ->orderBy('id', $direction)
            ->orderBy('ledger', $direction);
    }

    private function earningUnion(array $filters): QueryBuilder
    {
        $type = $filters['income_type'] ?? null;
        $branches = [];
        if ($type === null || $type === 'roi') {
            $branches[] = $this->roiBranch($filters);
            $branches[] = $this->packageRoiBranch($filters);
        }
        if ($type === null || $type === 'level') {
            $branches[] = $this->levelBranch($filters);
            $branches[] = $this->packageLevelBranch($filters);
        }
        if ($type === null || $type === 'wallet') {
            $branches[] = $this->walletBranch($filters);
        }

        $union = array_shift($branches);
        foreach ($branches as $branch) {
            $union->unionAll($branch);
        }

        return $union;
    }

    private function roiBranch(array $filters): QueryBuilder
    {
        $query = DB::table('roi_transactions')->select([
            'id',
            'member_id',
            'member_name',
            DB::raw("'ROI income' as income_type"),
            DB::raw('NULL as source_member'),
            DB::raw($this->legacySource().' as source_label'),
            DB::raw('NULL as level'),
            'income_amount as amount',
            DB::raw('NULL as currency'),
            'roi_date as business_date',
            'created_at as credited_at',
            'reference',
            'status',
            DB::raw("'Legacy investment' as ledger"),
        ]);
        $this->earningConstraints($query, $filters, 'roi_date', true);

        return $query;
    }

    private function levelBranch(array $filters): QueryBuilder
    {
        $query = DB::table('level_commission_transactions')->select([
            'id',
            'member_id',
            'member_name',
            DB::raw("'Level commission' as income_type"),
            DB::raw($this->personLabel('from_member_id', 'from_member_name').' as source_member'),
            DB::raw($this->legacySource().' as source_label'),
            'level',
            'income_amount as amount',
            DB::raw('NULL as currency'),
            'business_date',
            'created_at as credited_at',
            'reference',
            DB::raw('NULL as status'),
            DB::raw("'Legacy investment' as ledger"),
        ]);
        $this->earningConstraints($query, $filters, 'business_date', false);
        if (! empty($filters['status'])) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function walletBranch(array $filters): QueryBuilder
    {
        $query = DB::table('activation_wallet_transactions')
            ->where('type', 'credit')
            ->select([
                'id',
                'member_id',
                'member_name',
                DB::raw("'Wallet credit' as income_type"),
                DB::raw('NULL as source_member'),
                DB::raw("COALESCE(remarks, 'Activation wallet') as source_label"),
                DB::raw('NULL as level'),
                'amount',
                DB::raw('NULL as currency'),
                DB::raw('NULL as business_date'),
                'created_at as credited_at',
                'reference',
                DB::raw('NULL as status'),
                DB::raw("'Activation wallet' as ledger"),
            ]);
        $this->searchQuery($query, $filters['q'] ?? null, ['member_id', 'member_name', 'reference', 'remarks']);
        if (! empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }
        $this->betweenQuery($query, 'created_at', $filters);
        if (! empty($filters['package']) || ! empty($filters['currency']) || ! empty($filters['status'])) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    private function packageRoiBranch(array $filters): QueryBuilder
    {
        $query = DB::table('package_roi_accruals')->select([
            'id',
            'member_id',
            'member_name',
            DB::raw("'ROI income' as income_type"),
            DB::raw('NULL as source_member'),
            'package_name as source_label',
            DB::raw('NULL as level'),
            'amount',
            DB::raw("'USDT' as currency"),
            'business_date',
            'created_at as credited_at',
            'reference',
            'status',
            DB::raw("'Package purchase' as ledger"),
        ]);
        $this->packageEarningConstraints($query, $filters);

        return $query;
    }

    private function packageLevelBranch(array $filters): QueryBuilder
    {
        $query = DB::table('package_level_accruals')->select([
            'id',
            'member_id',
            'member_name',
            DB::raw("'Level commission' as income_type"),
            DB::raw($this->personLabel('from_member_id', 'from_member_name').' as source_member'),
            'package_name as source_label',
            'level',
            'amount',
            DB::raw("'USDT' as currency"),
            'business_date',
            'created_at as credited_at',
            'reference',
            'status',
            DB::raw("'Package purchase' as ledger"),
        ]);
        $this->packageEarningConstraints($query, $filters);

        return $query;
    }

    private function packageEarningConstraints(QueryBuilder $query, array $filters): void
    {
        $this->searchQuery($query, $filters['q'] ?? null, ['member_id', 'member_name', 'reference', 'package_name']);
        if (! empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('business_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate('business_date', '<=', $filters['to']);
        }
        if (! empty($filters['package'])) {
            $query->where('package_code', $filters['package']);
        }
        if (! empty($filters['status']) && $filters['status'] !== 'accrued') {
            $query->whereRaw('1 = 0');
        }
        if (! empty($filters['currency']) && $filters['currency'] !== 'USDT') {
            $query->whereRaw('1 = 0');
        }
    }

    private function earningConstraints(QueryBuilder $query, array $filters, string $dateColumn, bool $hasStatus): void
    {
        $this->searchQuery($query, $filters['q'] ?? null, ['member_id', 'member_name', 'reference', 'investment_id']);
        if (! empty($filters['member_id'])) {
            $query->where('member_id', $filters['member_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate($dateColumn, '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->whereDate($dateColumn, '<=', $filters['to']);
        }
        if ($hasStatus && ! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['package']) || ! empty($filters['currency'])) {
            $query->whereRaw('1 = 0');
        }
    }

    private function exportQuery(string $report, array $filters): Builder|QueryBuilder
    {
        return match ($report) {
            'members' => $this->memberQuery($filters),
            'packages' => ($filters['dataset'] ?? 'purchases') === 'catalog' ? $this->catalogQuery($filters) : $this->purchaseQuery($filters),
            'payments' => $this->purchaseQuery($filters),
            default => $this->earningQuery($filters),
        };
    }

    private function legacySource(): string
    {
        return $this->personLabel("'Legacy investment'", 'investment_id');
    }

    private function personLabel(string $left, string $right): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return '('.$left." || ' ' || ".$right.')';
        }

        return 'CONCAT('.$left.", ' ', ".$right.')';
    }

    /**
     * @return array<string, string>
     */
    private function present(string $report, object $row): array
    {
        if ($report === 'members') {
            return [
                'Member ID' => (string) $row->member_id,
                'Full name' => (string) $row->member_name,
                'Email' => (string) $row->email,
                'Mobile' => (string) $row->mobile_no,
                'Sponsor ID' => (string) $row->sponsor_id,
                'Sponsor name' => (string) $row->sponsor_name,
                'Registered' => $this->stamp($row->created_at),
                'Account status' => ucfirst((string) $row->status),
                'Approved package' => ! empty($row->has_approved_package) ? 'Approved' : 'No approved package',
                'Last login' => self::NOT_TRACKED,
                'Activation date' => self::NOT_TRACKED,
                'Activated by' => self::NOT_TRACKED,
            ];
        }

        if ($report === 'packages' && $row instanceof Package) {
            return [
                'Package name' => (string) $row->name,
                'Package type' => $row->typeLabel(),
                'Price' => MoneyDisplay::amount($row->price_usd),
                'Currency' => $row->denomination ?: self::NOT_STORED,
                'Code' => (string) $row->code,
                'Catalog status' => $row->is_active ? 'Active' : 'Inactive',
                'ROI eligible' => $row->roi_eligible ? 'Eligible' : 'Not eligible',
                'Level income eligible' => $row->level_commission_eligible ? 'Eligible' : 'Not eligible',
            ];
        }

        if ($report === 'packages') {
            return [
                'Package name' => (string) $row->package_name,
                'Package type' => Package::TYPES[$row->package_type] ?? ($row->package_type ?: self::NOT_STORED),
                'Price' => MoneyDisplay::amount($row->price_usd),
                'Currency' => $row->denomination ?: self::NOT_STORED,
                'Purchase ID' => (string) $row->reference,
                'Member ID' => (string) $row->member_id,
                'Member name' => (string) ($row->member->member_name ?? ''),
                'Purchased' => $this->stamp($row->submitted_at),
                'Payment status' => $row->paymentStatusLabel(),
                'Reviewed' => $row->reviewed_at ? $this->stamp($row->reviewed_at) : self::NOT_STORED,
                'Approved by' => (string) ($row->reviewer->name ?? self::NOT_STORED),
            ];
        }

        if ($report === 'payments') {
            return [
                'Payment reference' => (string) $row->reference,
                'Member ID' => (string) $row->member_id,
                'Member name' => (string) ($row->member->member_name ?? ''),
                'Package' => (string) $row->package_name,
                'Amount' => MoneyDisplay::amount($row->price_usd),
                'Currency' => $row->denomination ?: self::NOT_STORED,
                'Network' => $row->network ?: self::NOT_STORED,
                'Transaction ID' => (string) $row->payment_reference,
                'Submitted' => $this->stamp($row->submitted_at),
                'Status' => $row->paymentStatusLabel(),
                'Reviewed' => $row->reviewed_at ? $this->stamp($row->reviewed_at) : self::NOT_STORED,
                'Reviewed by' => (string) ($row->reviewer->name ?? self::NOT_STORED),
                'Rejection reason' => (string) ($row->rejection_reason ?: ''),
            ];
        }

        return [
            'Member ID' => (string) $row->member_id,
            'Member name' => (string) $row->member_name,
            'Income type' => (string) $row->income_type,
            'Source member' => (string) ($row->source_member ?: ''),
            'Source' => (string) $row->source_label,
            'Level' => $row->level === null ? '' : (string) $row->level,
            'Amount' => MoneyDisplay::amount($row->amount),
            'Currency' => filled($row->currency ?? null) ? (string) $row->currency : self::NOT_STORED,
            'Business date' => $row->business_date ? CarbonImmutable::parse($row->business_date)->format('d M Y') : self::NOT_STORED,
            'Credited' => $this->stamp($row->credited_at),
            'Reference' => (string) $row->reference,
            'Status' => (string) ($row->status ?: self::NOT_STORED),
            'Ledger' => (string) $row->ledger,
        ];
    }

    public function presentRow(string $report, object $row): array
    {
        return $this->present($report, $row);
    }

    private function packageConstraint(Builder $query, string $package): Builder
    {
        return $query->where(function (Builder $inner) use ($package): void {
            $inner->where('package_code', $package)->orWhere('package_id', $package);
        });
    }

    private function search(Builder $query, ?string $term, array $columns): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '\\%_').'%';
        $query->where(function (Builder $inner) use ($like, $columns): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $like);
            }
        });
    }

    private function searchQuery(QueryBuilder $query, ?string $term, array $columns): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '\\%_').'%';
        $query->where(function (QueryBuilder $inner) use ($like, $columns): void {
            foreach ($columns as $column) {
                $inner->orWhere($column, 'like', $like);
            }
        });
    }

    private function between(Builder $query, string $column, array $filters): void
    {
        if ($start = $this->start($filters['from'] ?? null)) {
            $query->where($column, '>=', $start);
        }
        if ($end = $this->end($filters['to'] ?? null)) {
            $query->where($column, '<=', $end);
        }
    }

    private function betweenQuery(QueryBuilder $query, string $column, array $filters): void
    {
        if ($start = $this->start($filters['from'] ?? null)) {
            $query->where($column, '>=', $start);
        }
        if ($end = $this->end($filters['to'] ?? null)) {
            $query->where($column, '<=', $end);
        }
    }

    private function sort(Builder $query, string $column, array $filters): Builder
    {
        $direction = ($filters['sort'] ?? 'newest') === 'oldest' ? 'asc' : 'desc';

        return $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    private function start(?string $date): ?CarbonImmutable
    {
        return $date ? CarbonImmutable::parse($date, config('deltasupport.report_timezone'))->startOfDay()->utc() : null;
    }

    private function end(?string $date): ?CarbonImmutable
    {
        return $date ? CarbonImmutable::parse($date, config('deltasupport.report_timezone'))->endOfDay()->utc() : null;
    }

    private function stamp(mixed $value): string
    {
        if ($value === null || $value === '') {
            return self::NOT_STORED;
        }

        return CarbonImmutable::parse($value)->timezone(config('deltasupport.report_timezone'))->format('d M Y, H:i');
    }

    /**
     * @return list<array{currency: string, total: string}>
     */
    private function currencyTotals(Builder $query, string $amountColumn): array
    {
        $rows = (clone $query)->reorder()->select('denomination')->selectRaw('SUM('.$amountColumn.') as total')->groupBy('denomination')->get();
        $totals = [];
        foreach ($rows as $row) {
            $totals[] = [
                'currency' => $row->denomination ?: self::NOT_STORED,
                'total' => MoneyDisplay::amount($row->total),
            ];
        }

        return $totals;
    }
}
