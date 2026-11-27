@extends('admin.layouts.app')
@section('title', 'Commission Settings')
@section('page-title', 'Commission Settings')
@section('content')
@php
    $editing = $editing ?? null;
    $isDraft = $editing && $editing->status === \App\Models\LevelIncomeRuleVersion::STATUS_DRAFT;
    $bands = $editing?->bands ?? collect();
    if ($bands->isEmpty()) {
        $bands = collect([
            (object)['start_level'=>1,'end_level'=>4,'min_qualifying_directs'=>1,'requires_personal_activation'=>true],
            (object)['start_level'=>5,'end_level'=>8,'min_qualifying_directs'=>2,'requires_personal_activation'=>true],
            (object)['start_level'=>9,'end_level'=>12,'min_qualifying_directs'=>3,'requires_personal_activation'=>true],
            (object)['start_level'=>13,'end_level'=>16,'min_qualifying_directs'=>4,'requires_personal_activation'=>true],
            (object)['start_level'=>17,'end_level'=>20,'min_qualifying_directs'=>5,'requires_personal_activation'=>true],
        ]);
    }
@endphp
<div class="ds-finance">
    <x-ui.page-header
        kicker="Level Income"
        title="Commission Settings"
        lead="Versioned unlock bands and 20-level commission percentages. Live financial:daily posting keeps hard-coded legacy rules until a published version is explicitly posting-authorized. Associate Commission posting stays disabled."
    />

    <div class="ds-stat-grid">
        <x-ui.stat label="Live unlock source" :value="$liveUsesHardcoded ? 'Hard-coded legacy' : 'Authorized DB version'" hint="{{ $authorized?->version_code ?? 'posting_authorized=false' }}" />
        <x-ui.stat label="Published baseline" :value="$published?->version_code ?? 'None'" hint="{{ $published?->label ?? 'No published version' }}" />
        <x-ui.stat label="Configuration version" :value="$editing?->version_code ?? '—'" hint="Status: {{ $editing?->status ?? 'n/a' }}" />
        <x-ui.stat label="Publication status" :value="$editing?->status ?? '—'" hint="Authorize is separate from publish" />
        <x-ui.stat label="Financial authorization" :value="($editing?->posting_authorized ?? false) ? 'Authorized' : 'Unauthorized'" hint="{{ ($preview['authorization_status'] ?? 'unauthorized') }}" />
        <x-ui.stat label="Applicability policy" :value="str_replace('_', ' ', (string) ($preview['applicability'] ?? $editing?->applicability ?? 'unspecified'))" hint="Recommended default: future purchases only" />
        <x-ui.stat label="Effective date" :value="$preview['effective_from'] ?? (optional($editing?->effective_from)?->toDateString() ?? 'Not set')" hint="Asia/Kolkata business date boundary" />
        <x-ui.stat label="Rate total" :value="$editTotal.'%'" hint="Must equal 100.0000% to publish" />
        <x-ui.stat label="Associate engine" :value="($associateEngine['mode'] ?? 'dry_run_only')" hint="Posting {{ ($associateEngine['posting_enabled'] ?? false) ? 'ENABLED' : 'disabled' }}" />
    </div>

    <x-ui.card title="Applicability impact" subtitle="Runtime accrual selects legacy vs authorized rules per source purchase and business date. Historical credits are never recalculated.">
        <div class="ds-form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.75rem;">
            <div>
                <p class="ds-muted" style="margin:0 0 .25rem;">Existing purchase impact</p>
                <p style="margin:0;">{{ $preview['existing_purchase_impact'] ?? '—' }}</p>
            </div>
            <div>
                <p class="ds-muted" style="margin:0 0 .25rem;">Future purchase impact</p>
                <p style="margin:0;">{{ $preview['future_purchase_impact'] ?? '—' }}</p>
            </div>
            <div>
                <p class="ds-muted" style="margin:0 0 .25rem;">Historical protection</p>
                <p style="margin:0;">{{ $preview['historical_protection'] ?? '—' }}</p>
            </div>
            <div>
                <p class="ds-muted" style="margin:0 0 .25rem;">Approved purchases on file</p>
                <p style="margin:0;">{{ number_format((int) ($preview['approved_purchase_count'] ?? 0)) }}</p>
            </div>
        </div>
    </x-ui.card>

    @if (session('status'))
        <x-ui.alert type="success"><p>{{ session('status') }}</p></x-ui.alert>
    @endif
    @if ($errors->any())
        <x-ui.alert type="warning">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif
    @if ($bandValidation !== [] || $rateValidation !== [])
        <x-ui.alert type="warning">
            @foreach (array_merge($bandValidation, $rateValidation) as $error)
                <p>{{ $error }}</p>
            @endforeach
        </x-ui.alert>
    @endif

    <x-ui.card title="Configuration tabs" subtitle="Draft edits never change live accrual. Publish stores the version; authorize posting is a separate explicit step.">
        <div class="ds-btn-row" style="flex-wrap:wrap;gap:.5rem;margin-bottom:1rem;">
            <a class="ds-btn {{ $tab === 'unlock' ? 'ds-btn-primary' : 'ds-btn-secondary' }}" href="{{ route('admin.finance.commission-settings', ['tab' => 'unlock', 'version' => $editing?->id]) }}">Level Unlock Rules</a>
            <a class="ds-btn {{ $tab === 'rates' ? 'ds-btn-primary' : 'ds-btn-secondary' }}" href="{{ route('admin.finance.commission-settings', ['tab' => 'rates', 'version' => $editing?->id]) }}">Commission Percentages</a>
            <a class="ds-btn {{ $tab === 'packages' ? 'ds-btn-primary' : 'ds-btn-secondary' }}" href="{{ route('admin.finance.commission-settings', ['tab' => 'packages', 'version' => $editing?->id]) }}">Package Commission Preview</a>
            <a class="ds-btn {{ $tab === 'history' ? 'ds-btn-primary' : 'ds-btn-secondary' }}" href="{{ route('admin.finance.commission-settings', ['tab' => 'history', 'version' => $editing?->id]) }}">Configuration History</a>
        </div>

        @if (in_array($tab, ['unlock', 'rates'], true) && $editing)
            <form method="POST" action="{{ route('admin.finance.level-income-rules.draft') }}" id="levelIncomeDraftForm">
                @csrf
                <input type="hidden" name="version_id" value="{{ $isDraft ? $editing->id : '' }}">
                <div class="ds-form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:1rem;">
                    <label>Version code
                        <input type="text" name="version_code" value="{{ old('version_code', $isDraft ? $editing->version_code : '') }}" {{ $isDraft ? 'readonly' : '' }} placeholder="auto if blank" maxlength="64">
                    </label>
                    <label>Label
                        <input type="text" name="label" value="{{ old('label', $editing->label) }}" required maxlength="160">
                    </label>
                    <label>Change note
                        <input type="text" name="change_note" value="{{ old('change_note', $editing->change_note) }}" maxlength="1000">
                    </label>
                </div>
                <label style="display:block;margin-bottom:1rem;">Notes
                    <textarea name="notes" rows="2" maxlength="2000">{{ old('notes', $editing->notes) }}</textarea>
                </label>

                @if ($tab === 'unlock')
                    <h3 style="margin:0 0 .5rem;">Direct qualification basis</h3>
                    <p class="ds-muted" style="margin:0 0 .75rem;">{{ $preview['qualification_note'] ?? 'Unique-member counting and package-purchase counting stay separate. Tour Only unlock does not enable ROI or Level Income generation.' }}</p>
                    <div class="ds-form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.75rem;margin-bottom:1rem;">
                        <label>Qualification basis
                            <select name="direct_qualification_basis" {{ $isDraft ? '' : 'disabled' }} required>
                                @foreach (($qualificationBases ?? []) as $basisKey => $basisLabel)
                                    <option value="{{ $basisKey }}" @selected(old('direct_qualification_basis', $editing->direct_qualification_basis ?? 'income_eligible_directs') === $basisKey)>{{ $basisLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label style="display:flex;align-items:flex-end;gap:.5rem;">
                            <input type="checkbox" name="tour_only_qualifies_for_unlock" value="1" @checked(old('tour_only_qualifies_for_unlock', $editing->tour_only_qualifies_for_unlock ?? false)) {{ $isDraft ? '' : 'disabled' }}>
                            <span>Approved Tour Only packages qualify for level unlocking (not ROI/commission generation)</span>
                        </label>
                        <label style="display:flex;align-items:flex-end;gap:.5rem;">
                            <input type="checkbox" name="grandfather_existing_unlock" value="1" @checked(old('grandfather_existing_unlock', $editing->grandfather_existing_unlock ?? false)) {{ $isDraft ? '' : 'disabled' }}>
                            <span>Grandfather existing legacy unlock (prevent migration-only reductions)</span>
                        </label>
                    </div>

                    <h3 style="margin:1rem 0 .5rem;">Combined joining + purchase contributions</h3>
                    <p class="ds-muted" style="margin:0 0 .75rem;">Used only when basis is “Combined: direct joining + package purchases”. Exact formula requires client approval before authorize. Staged settings do not change live legacy unlock.</p>
                    <div class="ds-form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.75rem;margin-bottom:1rem;">
                        <label style="display:flex;align-items:flex-end;gap:.5rem;">
                            <input type="checkbox" name="combined_joining_enabled" value="1" @checked(old('combined_joining_enabled', $editing->combined_joining_enabled ?? false)) {{ $isDraft ? '' : 'disabled' }}>
                            <span>Count direct joining (registered directs)</span>
                        </label>
                        <label style="display:flex;align-items:flex-end;gap:.5rem;">
                            <input type="checkbox" name="combined_purchase_enabled" value="1" @checked(old('combined_purchase_enabled', $editing->combined_purchase_enabled ?? false)) {{ $isDraft ? '' : 'disabled' }}>
                            <span>Count package purchases independently</span>
                        </label>
                        <label>Purchase scope
                            <select name="combined_purchase_scope" {{ $isDraft ? '' : 'disabled' }}>
                                @foreach (($purchaseScopeLabels ?? []) as $scopeKey => $scopeLabel)
                                    <option value="{{ $scopeKey }}" @selected(old('combined_purchase_scope', $editing->combined_purchase_scope ?? 'approved_package_directs') === $scopeKey)>{{ $scopeLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Repeat purchase mode
                            <select name="combined_repeat_purchase_mode" {{ $isDraft ? '' : 'disabled' }}>
                                @foreach (($repeatModeLabels ?? []) as $modeKey => $modeLabel)
                                    <option value="{{ $modeKey }}" @selected(old('combined_repeat_purchase_mode', $editing->combined_repeat_purchase_mode ?? 'unique_directs') === $modeKey)>{{ $modeLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Double-credit policy
                            <select name="combined_double_credit_policy" {{ $isDraft ? '' : 'disabled' }}>
                                @foreach (($doubleCreditPolicyLabels ?? []) as $policyKey => $policyLabel)
                                    <option value="{{ $policyKey }}" @selected(old('combined_double_credit_policy', $editing->combined_double_credit_policy ?? 'prevent_overlap') === $policyKey)>{{ $policyLabel }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Joining unit value
                            <input type="number" min="0" max="100" name="combined_joining_unit_value" value="{{ old('combined_joining_unit_value', $editing->combined_joining_unit_value ?? 1) }}" {{ $isDraft ? '' : 'readonly' }}>
                        </label>
                        <label>Purchase unit value
                            <input type="number" min="0" max="100" name="combined_purchase_unit_value" value="{{ old('combined_purchase_unit_value', $editing->combined_purchase_unit_value ?? 1) }}" {{ $isDraft ? '' : 'readonly' }}>
                        </label>
                    </div>

                    @if (! $isDraft)
                        <input type="hidden" name="direct_qualification_basis" value="{{ $editing->direct_qualification_basis ?? 'income_eligible_directs' }}">
                        @if ($editing->tour_only_qualifies_for_unlock ?? false)
                            <input type="hidden" name="tour_only_qualifies_for_unlock" value="1">
                        @endif
                        @if ($editing->grandfather_existing_unlock ?? false)
                            <input type="hidden" name="grandfather_existing_unlock" value="1">
                        @endif
                        @if ($editing->combined_joining_enabled ?? false)
                            <input type="hidden" name="combined_joining_enabled" value="1">
                        @endif
                        @if ($editing->combined_purchase_enabled ?? false)
                            <input type="hidden" name="combined_purchase_enabled" value="1">
                        @endif
                        <input type="hidden" name="combined_purchase_scope" value="{{ $editing->combined_purchase_scope ?? 'approved_package_directs' }}">
                        <input type="hidden" name="combined_repeat_purchase_mode" value="{{ $editing->combined_repeat_purchase_mode ?? 'unique_directs' }}">
                        <input type="hidden" name="combined_double_credit_policy" value="{{ $editing->combined_double_credit_policy ?? 'prevent_overlap' }}">
                        <input type="hidden" name="combined_joining_unit_value" value="{{ $editing->combined_joining_unit_value ?? 1 }}">
                        <input type="hidden" name="combined_purchase_unit_value" value="{{ $editing->combined_purchase_unit_value ?? 1 }}">
                    @endif
                    <div class="ds-stat-grid" style="margin-bottom:1rem;">
                        <x-ui.stat label="Draft basis" :value="str_replace('_', ' ', (string) ($preview['direct_qualification_basis'] ?? 'income_eligible_directs'))" hint="{{ $preview['direct_qualification_basis_label'] ?? '' }}" />
                        <x-ui.stat label="Tour Only unlock" :value="($preview['tour_only_qualifies_for_unlock'] ?? false) ? 'Included' : 'Excluded'" hint="Independent of ROI eligibility" />
                        <x-ui.stat label="Grandfathering" :value="($preview['grandfather_existing_unlock'] ?? false) ? 'Enabled' : 'Off'" hint="{{ ($preview['grandfather_summary']['captured'] ?? false) ? 'Baseline captured' : 'Capture required before authorize' }}" />
                        <x-ui.stat label="Combined contributions" :value="(($preview['combined_joining_enabled'] ?? false) || ($preview['combined_purchase_enabled'] ?? false)) ? 'Staged' : 'Off'" hint="Requires basis=combined + BD formula approval" />
                    </div>

                    <h3 style="margin:1rem 0 .5rem;">Existing member level protection</h3>
                    <p class="ds-muted" style="margin:0 0 .75rem;">{{ $preview['grandfather_note'] ?? 'protected = max(proposed, min(snapshot, current legacy)). Capture does not authorize posting or backfill history.' }}</p>
                    <div class="ds-stat-grid" style="margin-bottom:1rem;">
                        <x-ui.stat label="Transition date" :value="$preview['grandfather_summary']['transition_date'] ?? (optional($editing->grandfather_transition_date)?->toDateString() ?? 'Not captured')" />
                        <x-ui.stat label="Capture mode" :value="$preview['grandfather_summary']['capture_mode'] ?? '—'" hint="{{ $preview['grandfather_summary']['capture_note'] ?? '' }}" />
                        <x-ui.stat label="Snapshots" :value="(string) ($preview['grandfather_summary']['snapshot_count'] ?? 0)" hint="{{ (string) ($preview['grandfather_summary']['with_unlock_count'] ?? 0) }} with legacy unlock" />
                        <x-ui.stat label="Captured at" :value="$preview['grandfather_summary']['captured_at'] ?? '—'" />
                        <x-ui.stat
                            label="Baseline fresh"
                            :value="!empty($preview['grandfather_summary']['freshness']['is_fresh']) ? 'Yes' : (!empty($preview['grandfather_summary']['freshness']['requires_recapture']) ? 'No — recapture required' : (($preview['grandfather_summary']['captured'] ?? false) ? 'Review' : 'N/A'))"
                            hint="{{ ($preview['grandfather_summary']['freshness']['capture_mode'] ?? null) === null && ($preview['grandfather_summary']['captured'] ?? false) ? 'Capture mode missing (NULL) — fail closed' : (!empty($preview['grandfather_summary']['freshness']['requires_recapture']) ? 'Recapture required' : (!empty($preview['grandfather_summary']['freshness']['requires_pre_cutover_ack']) ? 'Ack or recapture' : ($preview['grandfather_summary']['freshness']['business_timezone'] ?? ''))) }}"
                        />
                    </div>
                    @if (!empty($preview['member_unlock_impact_sample']))
                        <x-ui.table>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Joining</th>
                                        <th>Purchase</th>
                                        <th>Combined</th>
                                        <th>Current (legacy)</th>
                                        <th>Proposed</th>
                                        <th>Protected</th>
                                        <th>Impact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach ($preview['member_unlock_impact_sample'] as $impactRow)
                                    <tr>
                                        <td>{{ $impactRow['member_id'] }}</td>
                                        <td>{{ $impactRow['joining_credits'] ?? $impactRow['joining_count'] ?? '—' }}</td>
                                        <td>{{ $impactRow['purchase_credits'] ?? $impactRow['purchase_count'] ?? '—' }}</td>
                                        <td>{{ $impactRow['combined_credits'] ?? '—' }}</td>
                                        <td>{{ $impactRow['current_legacy'] }}</td>
                                        <td>{{ $impactRow['proposed'] }}</td>
                                        <td>{{ $impactRow['protected'] }}</td>
                                        <td>{{ $impactRow['impact'] }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </x-ui.table>
                    @endif

                    <h3 style="margin:0 0 .5rem;">Unlock bands (levels 1–20, no gaps/overlaps)</h3>
                    <x-ui.table>
                        <table>
                            <thead>
                                <tr>
                                    <th>Start</th>
                                    <th>End</th>
                                    <th>Min qualifying directs</th>
                                    <th>Requires personal activation</th>
                                </tr>
                            </thead>
                            <tbody id="unlockBandsBody">
                            @foreach ($bands as $i => $band)
                                <tr>
                                    <td><input type="number" name="bands[{{ $i }}][start_level]" min="1" max="20" value="{{ old('bands.'.$i.'.start_level', $band->start_level) }}" required></td>
                                    <td><input type="number" name="bands[{{ $i }}][end_level]" min="1" max="20" value="{{ old('bands.'.$i.'.end_level', $band->end_level) }}" required></td>
                                    <td><input type="number" name="bands[{{ $i }}][min_qualifying_directs]" min="0" max="100" value="{{ old('bands.'.$i.'.min_qualifying_directs', $band->min_qualifying_directs) }}" required></td>
                                    <td>
                                        <label><input type="checkbox" name="bands[{{ $i }}][requires_personal_activation]" value="1" @checked(old('bands.'.$i.'.requires_personal_activation', $band->requires_personal_activation))> Required</label>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </x-ui.table>
                    <div class="ds-btn-row" style="gap:.5rem;margin:.75rem 0;">
                        <button type="button" class="ds-btn ds-btn-secondary" id="addUnlockBand">Add band</button>
                        <button type="button" class="ds-btn ds-btn-secondary" id="removeUnlockBand">Remove last</button>
                    </div>

                    <h3 style="margin:1rem 0 .5rem;">Live vs draft ladder (activated member)</h3>
                    <x-ui.table>
                        <table>
                            <thead>
                                <tr>
                                    <th>Qualifying directs</th>
                                    <th>Legacy / live</th>
                                    <th>Draft (if activated)</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($unlockLadder as $row)
                                <tr>
                                    <td>{{ $row['directs'] }}</td>
                                    <td>{{ $row['live'] }} of 20</td>
                                    <td>{{ $row['draft_active'] }} of 20</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </x-ui.table>
                @endif

                @if ($tab === 'rates')
                    <h3 style="margin:0 0 .5rem;">Commission percentages (4 decimal places, total 100%)</h3>
                    <x-ui.table>
                        <table>
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th class="ds-money">Percentage</th>
                                    <th class="ds-money">Legacy baseline</th>
                                </tr>
                            </thead>
                            <tbody>
                            @for ($level = 1; $level <= $maxLevels; $level++)
                                <tr>
                                    <td>L{{ $level }}</td>
                                    <td class="ds-money">
                                        <input class="js-rate-input" data-level="{{ $level }}" type="number" step="0.0001" min="0" name="rates[{{ $level }}]" value="{{ old('rates.'.$level, $editRates[$level] ?? '0.0000') }}" required style="width:7rem;text-align:right;">
                                    </td>
                                    <td class="ds-money">{{ $legacyRates[$level] ?? '—' }}%</td>
                                </tr>
                            @endfor
                                <tr>
                                    <td>Total</td>
                                    <td class="ds-money"><strong id="rateTotalDisplay">{{ $editTotal }}%</strong></td>
                                    <td class="ds-money">100.0000%</td>
                                </tr>
                            </tbody>
                        </table>
                    </x-ui.table>
                @else
                    {{-- Keep rates in form when editing unlock tab --}}
                    @for ($level = 1; $level <= $maxLevels; $level++)
                        <input type="hidden" name="rates[{{ $level }}]" value="{{ old('rates.'.$level, $editRates[$level] ?? ($legacyRates[$level] ?? '0.0000')) }}">
                    @endfor
                @endif

                @if ($tab === 'rates')
                    {{-- Keep bands + qualification in form when editing rates tab --}}
                    <input type="hidden" name="direct_qualification_basis" value="{{ old('direct_qualification_basis', $editing->direct_qualification_basis ?? 'income_eligible_directs') }}">
                    @if (old('tour_only_qualifies_for_unlock', $editing->tour_only_qualifies_for_unlock ?? false))
                        <input type="hidden" name="tour_only_qualifies_for_unlock" value="1">
                    @endif
                    @if (old('grandfather_existing_unlock', $editing->grandfather_existing_unlock ?? false))
                        <input type="hidden" name="grandfather_existing_unlock" value="1">
                    @endif
                    @if (old('combined_joining_enabled', $editing->combined_joining_enabled ?? false))
                        <input type="hidden" name="combined_joining_enabled" value="1">
                    @endif
                    @if (old('combined_purchase_enabled', $editing->combined_purchase_enabled ?? false))
                        <input type="hidden" name="combined_purchase_enabled" value="1">
                    @endif
                    <input type="hidden" name="combined_purchase_scope" value="{{ old('combined_purchase_scope', $editing->combined_purchase_scope ?? 'approved_package_directs') }}">
                    <input type="hidden" name="combined_repeat_purchase_mode" value="{{ old('combined_repeat_purchase_mode', $editing->combined_repeat_purchase_mode ?? 'unique_directs') }}">
                    <input type="hidden" name="combined_double_credit_policy" value="{{ old('combined_double_credit_policy', $editing->combined_double_credit_policy ?? 'prevent_overlap') }}">
                    <input type="hidden" name="combined_joining_unit_value" value="{{ old('combined_joining_unit_value', $editing->combined_joining_unit_value ?? 1) }}">
                    <input type="hidden" name="combined_purchase_unit_value" value="{{ old('combined_purchase_unit_value', $editing->combined_purchase_unit_value ?? 1) }}">
                    @foreach ($bands as $i => $band)
                        <input type="hidden" name="bands[{{ $i }}][start_level]" value="{{ $band->start_level }}">
                        <input type="hidden" name="bands[{{ $i }}][end_level]" value="{{ $band->end_level }}">
                        <input type="hidden" name="bands[{{ $i }}][min_qualifying_directs]" value="{{ $band->min_qualifying_directs }}">
                        @if ($band->requires_personal_activation)
                            <input type="hidden" name="bands[{{ $i }}][requires_personal_activation]" value="1">
                        @endif
                    @endforeach
                @endif

                <div class="ds-btn-row" style="gap:.5rem;margin-top:1rem;flex-wrap:wrap;">
                    <button type="submit" class="ds-btn ds-btn-primary" {{ $isDraft || ! $editing->isPublished() ? '' : 'disabled' }}>Save draft</button>
                    @if (! $isDraft)
                        <span class="ds-muted">Clone a published version to edit, or open the proposed draft.</span>
                    @endif
                </div>
            </form>

            @if ($editing)
                <form method="POST" action="{{ route('admin.finance.level-income-rules.clone') }}" style="margin-top:.75rem;">
                    @csrf
                    <input type="hidden" name="version_id" value="{{ $editing->id }}">
                    <button type="submit" class="ds-btn ds-btn-secondary">Clone as new draft</button>
                </form>
            @endif

            @if ($editing && ($editing->grandfather_existing_unlock ?? false) && ! ($editing->posting_authorized ?? false))
                <x-ui.card title="Capture grandfather baseline" subtitle="For existing_prospective, use the same date as effective_from. Dates on/before today reconstruct eligibility as-of that day. Future dates freeze current eligibility as a pre-cutover baseline (not an exact future snapshot) — recapture on/after the transition date before authorize when possible. Blocked after authorization. Does not backfill income or change wallets." style="margin-top:1rem;">
                    @if (!empty($preview['grandfather_summary']['freshness']['reasons']))
                        <x-ui.alert type="warning" style="margin-bottom:.75rem;">
                            <p><strong>Baseline freshness:</strong></p>
                            <ul style="margin:0;padding-left:1.2rem;">
                                @foreach ($preview['grandfather_summary']['freshness']['reasons'] as $reason)
                                    <li>{{ $reason }}</li>
                                @endforeach
                            </ul>
                        </x-ui.alert>
                    @endif
                    <form method="POST" action="{{ route('admin.finance.level-income-rules.grandfather-capture') }}">
                        @csrf
                        <input type="hidden" name="version_id" value="{{ $editing->id }}">
                        <div class="ds-form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;margin-bottom:.75rem;">
                            <label>Transition date
                                <input type="date" name="transition_date" value="{{ old('transition_date', optional($editing->effective_from)?->toDateString() ?? optional($editing->grandfather_transition_date)?->toDateString() ?? now()->toDateString()) }}" required>
                            </label>
                        </div>
                        @if (($preview['grandfather_summary']['capture_mode'] ?? null))
                            <p class="ds-muted" style="margin:0 0 .75rem;">Last capture mode: {{ $preview['grandfather_summary']['capture_mode'] }}. {{ $preview['grandfather_summary']['capture_note'] ?? '' }}</p>
                        @endif
                        <label style="display:flex;gap:.5rem;align-items:flex-start;margin-bottom:.75rem;">
                            <input type="checkbox" name="confirm_capture" value="1" required>
                            <span>I confirm this captures legacy unlock levels only, replaces any prior unauthorized baseline for this version, and does not authorize financial posting or recalculate history.</span>
                        </label>
                        <button type="submit" class="ds-btn ds-btn-secondary">Capture baseline</button>
                    </form>
                </x-ui.card>
            @endif
        @endif

        @if ($tab === 'packages')
            <x-ui.alert type="info">
                <p>Read-only simulator using applicable package plan definitions (PackageIncomeDefinitionResolver / authorized package plans). Pool and duration come from the package definition — not derived from ROI or price unless that definition specifies it.</p>
                <p>{{ $preview['historical_protection'] }} Approved purchases counted: {{ $preview['approved_purchase_count'] }}.</p>
            </x-ui.alert>
            <h3 style="margin:1rem 0 .5rem;">Unlock impact scenarios</h3>
            <x-ui.table>
                <table>
                    <thead>
                        <tr>
                            <th>Scenario</th>
                            <th>Legacy unlocked</th>
                            <th>Candidate unlocked</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($preview['scenarios'] as $scenario)
                        <tr>
                            <td>{{ $scenario['label'] }}</td>
                            <td>{{ $scenario['legacy_unlocked'] }}</td>
                            <td>{{ $scenario['candidate_unlocked'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </x-ui.table>
            @forelse ($preview['package_previews'] as $pool)
                <x-ui.card :title="$pool['name'].' daily distribution preview'" :subtitle="'Pool '.$pool['pool'].' USDT · '.($pool['days'] ?? '—').' days · source '.($pool['source'] ?? 'n/a')">
                    <x-ui.table>
                        <table>
                            <thead>
                                <tr>
                                    <th>Level</th>
                                    <th class="ds-money">Rate</th>
                                    <th class="ds-money">Daily USDT</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($pool['levels'] as $level)
                                <tr>
                                    <td>{{ $level['level'] }}</td>
                                    <td class="ds-money">{{ $level['rate'] }}%</td>
                                    <td class="ds-money">{{ $level['amount'] }}</td>
                                </tr>
                            @endforeach
                                <tr>
                                    <td>Total</td>
                                    <td class="ds-money">100.0000%</td>
                                    <td class="ds-money">{{ $pool['daily_sum'] }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </x-ui.table>
                </x-ui.card>
            @empty
                <p>No level-commission-eligible packages with a resolvable commission pool were found.</p>
            @endforelse
        @endif

        @if ($tab === 'history')
            <h3 style="margin:0 0 .5rem;">Versions</h3>
            <x-ui.table>
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Status</th>
                            <th>Posting</th>
                            <th>Applicability</th>
                            <th>Effective</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($versions as $version)
                        <tr>
                            <td>{{ $version->version_code }}<br><span class="ds-muted">{{ $version->label }}</span></td>
                            <td>{{ $version->status }}</td>
                            <td>{{ $version->posting_authorized ? 'authorized' : 'off' }}</td>
                            <td>{{ $version->applicability }}</td>
                            <td>{{ optional($version->effective_from)?->toDateString() ?? '—' }}</td>
                            <td><a href="{{ route('admin.finance.commission-settings', ['tab' => 'unlock', 'version' => $version->id]) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </x-ui.table>

            @if ($editing && $editing->isDraft())
                <x-ui.card title="Publish draft" subtitle="Publishing does not authorize financial:daily. Recommended default applicability is future_purchases_only.">
                    <form method="POST" action="{{ route('admin.finance.level-income-rules.publish') }}">
                        @csrf
                        <input type="hidden" name="version_id" value="{{ $editing->id }}">
                        <div class="ds-form-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:.75rem;">
                            <label>Applicability
                                <select name="applicability" required>
                                    <option value="future_purchases_only" @selected(old('applicability', $editing->applicability ?: 'future_purchases_only') === 'future_purchases_only')>Future approved purchases only (recommended)</option>
                                    <option value="existing_prospective" @selected(old('applicability', $editing->applicability) === 'existing_prospective')>Existing members prospectively from effective date</option>
                                    <option value="unspecified" @selected(old('applicability', $editing->applicability) === 'unspecified')>Unspecified (cannot authorize posting)</option>
                                </select>
                            </label>
                            <label>Effective from
                                <input type="date" name="effective_from" value="{{ old('effective_from', optional($editing->effective_from)?->toDateString()) }}">
                            </label>
                            <label>Change note
                                <input type="text" name="change_note" maxlength="1000">
                            </label>
                        </div>
                        <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="confirm_publish" value="1" required> I confirm this publish is intentional and does not activate posting.</label>
                        <button type="submit" class="ds-btn ds-btn-primary" @disabled($bandValidation !== [] || $rateValidation !== [])>Publish configuration</button>
                    </form>
                </x-ui.card>
            @endif

            @if ($editing && $editing->isPublished())
                <x-ui.card title="Posting authorization" subtitle="Stop before activating new business rules unless business approval is explicit. This control is intentionally separate from publish. Runtime enforcement uses purchase approval + effective date.">
                    @if (! $editing->posting_authorized)
                        <form method="POST" action="{{ route('admin.finance.level-income-rules.authorize') }}">
                            @csrf
                            <input type="hidden" name="version_id" value="{{ $editing->id }}">
                            <p>Current policy: <strong>{{ $editing->applicability }}</strong> / effective <strong>{{ optional($editing->effective_from)?->toDateString() ?? 'no date' }}</strong>.</p>
                            <p class="ds-muted">{{ $preview['existing_purchase_impact'] ?? '' }}</p>
                            <p class="ds-muted">{{ $preview['future_purchase_impact'] ?? '' }}</p>
                            <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="confirm_impact_preview" value="1" required> I reviewed the applicability impact preview above (existing vs future purchases).</label>
                            @if ($editing->applicability === 'existing_prospective')
                                <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="confirm_existing_prospective" value="1" required> I confirm existing_prospective: new rules apply from the effective business date for all source purchases with no historical backfill. Grandfather transition date must match effective_from.</label>
                            @endif
                            @if (!empty($preview['grandfather_summary']['freshness']['requires_pre_cutover_ack']))
                                <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="confirm_pre_cutover_baseline" value="1" required> I acknowledge post-capture membership/eligibility drift: new members use proposed rules only; protection remains max(proposed, min(snapshot, current_legacy)). Prefer recapture for a clean cutover.</label>
                            @endif
                            <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="confirm_authorize" value="1" required> I authorize this version for financial:daily Level Income posting (no historical backfill).</label>
                            <button type="submit" class="ds-btn ds-btn-primary">Authorize posting</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.finance.level-income-rules.revoke') }}">
                            @csrf
                            <input type="hidden" name="version_id" value="{{ $editing->id }}">
                            <p>This version is currently authorized for posting.</p>
                            <button type="submit" class="ds-btn ds-btn-secondary">Revoke posting authorization</button>
                        </form>
                    @endif
                </x-ui.card>
            @endif

            <h3 style="margin:1.25rem 0 .5rem;">Audit log</h3>
            <x-ui.table>
                <table>
                    <thead>
                        <tr>
                            <th>When</th>
                            <th>Action</th>
                            <th>Version</th>
                            <th>Actor</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($audits as $audit)
                        <tr>
                            <td>{{ $audit->created_at }}</td>
                            <td>{{ $audit->action }}</td>
                            <td>{{ $audit->version?->version_code ?? '—' }}</td>
                            <td>{{ $audit->actor?->email ?? 'system' }}</td>
                        </tr>
                    @empty
                        <tr><td class="ds-empty-cell" colspan="4">No audits yet.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </x-ui.table>

            <x-ui.card title="Associate Commission Structure" :subtitle="'Version '.$associate['version_code'].' · '.$associate['commission_days'].' days · dry_run_only'">
                <p>{{ $associate['interpretation'] ?? 'Associate structure remains separate. Posting stays disabled.' }}</p>
                <p>Stored percentage plan integrity: {{ ($integrityErrors ?? []) === [] ? 'OK' : implode('; ', $integrityErrors) }}. Percentage plan: {{ $active ? 'Active' : 'Inactive' }}.</p>
                @foreach ($associate['tiers'] as $tier)
                    <h3 style="margin: 1rem 0 0.5rem;">{{ $tier['label'] }} · {{ $tier['package_price'] }} USDT</h3>
                    <p>
                        Mapped code: {{ $tier['mapped_package_code'] ?: 'Unmapped' }}.
                        Daily total {{ $tier['daily_total'] }} USDT.
                        Max 400-day total {{ $tier['duration_total'] }} USDT (theoretical — not posted income).
                    </p>
                    <x-ui.table>
                        <table>
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th class="ds-money">Daily USDT</th>
                                    <th class="ds-money">400-day USDT</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($tier['ranks'] as $rank)
                                <tr>
                                    <td>{{ $rank['rank'] }}</td>
                                    <td class="ds-money">{{ $rank['daily_amount'] }}</td>
                                    <td class="ds-money">{{ $rank['duration_amount'] }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </x-ui.table>
                @endforeach
            </x-ui.card>
        @endif
    </x-ui.card>
</div>
@if (in_array($tab, ['unlock', 'rates'], true))
<script>
(() => {
    const totalEl = document.getElementById('rateTotalDisplay');
    const inputs = () => [...document.querySelectorAll('.js-rate-input')];
    const recalc = () => {
        if (!totalEl) return;
        let sum = 0;
        inputs().forEach((el) => { sum += Number(el.value || 0); });
        totalEl.textContent = sum.toFixed(4) + '%';
        totalEl.style.color = Math.abs(sum - 100) < 0.00005 ? '' : '#b45309';
    };
    inputs().forEach((el) => el.addEventListener('input', recalc));
    recalc();

    const body = document.getElementById('unlockBandsBody');
    const addBtn = document.getElementById('addUnlockBand');
    const removeBtn = document.getElementById('removeUnlockBand');
    if (body && addBtn) {
        addBtn.addEventListener('click', () => {
            const i = body.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="number" name="bands[${i}][start_level]" min="1" max="20" required></td>
                <td><input type="number" name="bands[${i}][end_level]" min="1" max="20" required></td>
                <td><input type="number" name="bands[${i}][min_qualifying_directs]" min="0" max="100" value="0" required></td>
                <td><label><input type="checkbox" name="bands[${i}][requires_personal_activation]" value="1" checked> Required</label></td>`;
            body.appendChild(tr);
        });
    }
    if (body && removeBtn) {
        removeBtn.addEventListener('click', () => {
            const rows = body.querySelectorAll('tr');
            if (rows.length > 1) rows[rows.length - 1].remove();
        });
    }
})();
</script>
@endif
@endsection
