{{--
    Newtheme floating action button — expanding launcher for the create
    actions (New Quotation / New Task / New Visit). Mirrors the FAB markup
    rendered by public/newtheme/assets/menu.js so it picks up the existing
    .shf-fab-* CSS in shf-workflow.css.
--}}
@php
    $u = auth()->user();
    $items = [];

    if ($u->hasPermission('create_quotation')) {
        $items[] = [
            'key' => 'quotation',
            'label' => 'New Quotation',
            'url' => route('quotations.create'),   // navigates — multi-step form
            'modal' => null,
            'iconCls' => 'shf-fab-icon-quotation',
            'iconPath' => 'M9 12h6m-3-3v6m-4 6h8a2 2 0 002-2V7.414A1 1 0 0017.707 6.707l-3.414-3.414A1 1 0 0013.586 3H8a2 2 0 00-2 2v14a2 2 0 002 2z',
        ];
    }

    // Tasks have no permission gate — everyone can create.
    $items[] = [
        'key' => 'task',
        'label' => 'New Task',
        'url' => '#',
        'modal' => 'create-task',           // shared modal opens in place
        'iconCls' => 'shf-fab-icon-task',
        'iconPath' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    ];

    if ($u->hasPermission('create_dvr')) {
        $items[] = [
            'key' => 'visit',
            'label' => 'New Visit',
            'url' => '#',
            'modal' => 'create-dvr',        // shared modal opens in place
            'iconCls' => 'shf-fab-icon-visit',
            'iconPath' => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z',
        ];
    }
@endphp

@if (count($items) > 0)
    <div class="shf-fab-backdrop" aria-hidden="true"></div>

    <div class="shf-fab-wrap">
        @if (count($items) === 1)
            {{-- Single create action: tap goes straight to it. --}}
            @php($only = $items[0])
            <a class="shf-fab-main" href="{{ $only['url'] }}" aria-label="{{ $only['label'] }}" title="{{ $only['label'] }}">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </a>
        @else
            <div class="shf-fab-menu" role="menu">
                @foreach ($items as $it)
                    @if (! empty($it['modal']))
                        {{-- Modal trigger — opens the shared modal in place on the current page --}}
                        <button type="button" class="shf-fab-item" role="menuitem" data-shf-open="{{ $it['modal'] }}">
                            <span class="shf-fab-item-icon {{ $it['iconCls'] }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $it['iconPath'] }}"/>
                                </svg>
                            </span>
                            <span class="shf-fab-item-label">{{ $it['label'] }}</span>
                        </button>
                    @else
                        {{-- Navigation link — e.g. quotation creation (multi-step) --}}
                        <a class="shf-fab-item" href="{{ $it['url'] }}" role="menuitem">
                            <span class="shf-fab-item-icon {{ $it['iconCls'] }}">
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $it['iconPath'] }}"/>
                                </svg>
                            </span>
                            <span class="shf-fab-item-label">{{ $it['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>

            <button type="button" class="shf-fab-main" id="shfFabMain" aria-label="Create menu" aria-expanded="false">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </button>
        @endif
    </div>
@endif
