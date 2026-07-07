// ============================================================
//  CONFIG LOADER — Fetches from /api/config/public (Laravel API)
//  Falls back to IndexedDB cache (offline), then defaults.
// ============================================================
const ConfigLoader = {

    load: async function() {
        try {
            var response = await fetch('/api/config/public?t=' + Date.now());
            if (!response.ok) throw new Error('Failed to fetch config');
            var saved = await response.json();
            var merged = this.mergeWithDefaults(saved);
            // Cache to IndexedDB for offline use
            if (typeof OfflineManager !== 'undefined') {
                OfflineManager.cacheConfig(merged);
            }
            return merged;
        } catch(e) {
            console.warn('Config load from server failed, trying offline cache:', e);
            // Try IndexedDB cache
            if (typeof OfflineManager !== 'undefined') {
                try {
                    var cached = await OfflineManager.getCachedConfig();
                    if (cached) return cached;
                } catch(e2) {
                    console.warn('IndexedDB cache miss:', e2);
                }
            }
            return JSON.parse(JSON.stringify(CONFIG_DEFAULTS));
        }
    },

    save: async function(configObj) {
        // In Laravel, config is saved via individual settings routes with CSRF
        // This method is kept for offline compatibility
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        var response = await fetch('/settings/company', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ config: configObj })
        });
        var result = await response.json();
        if (!response.ok) {
            throw new Error(result.error || 'Failed to save config');
        }
        // Update IndexedDB cache
        if (typeof OfflineManager !== 'undefined') {
            OfflineManager.cacheConfig(configObj);
        }
        return result;
    },

    mergeWithDefaults: function(saved) {
        var result = JSON.parse(JSON.stringify(
            typeof CONFIG_DEFAULTS !== 'undefined' ? CONFIG_DEFAULTS : {}
        ));
        var keys = ['companyName', 'companyAddress', 'companyPhone', 'companyEmail',
                      'banks', 'tenures', 'iomCharges', 'gstPercent', 'ourServices',
                      'documents_en', 'documents_gu', 'bankCharges'];
        keys.forEach(function(key) {
            if (saved[key] !== undefined) result[key] = saved[key];
        });
        if (result.tenures) result.tenures.sort(function(a, b) { return a - b; });
        return result;
    }
};
