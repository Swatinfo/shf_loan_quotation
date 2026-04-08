// ============================================================
//  OFFLINE MANAGER — IndexedDB + online/offline detection + sync
//  Updated for Laravel routes with CSRF token support
// ============================================================
var OfflineManager = {

    DB_NAME: 'shf_offline',
    DB_VERSION: 2,
    _db: null,

    // ---- IndexedDB Setup ----
    openDB: function() {
        var self = this;
        if (self._db) return Promise.resolve(self._db);

        return new Promise(function(resolve, reject) {
            var req = indexedDB.open(self.DB_NAME, self.DB_VERSION);
            req.onupgradeneeded = function(e) {
                var db = e.target.result;
                if (!db.objectStoreNames.contains('cached_config')) {
                    db.createObjectStore('cached_config', { keyPath: 'key' });
                }
                if (!db.objectStoreNames.contains('cached_charges')) {
                    db.createObjectStore('cached_charges', { keyPath: 'key' });
                }
                if (!db.objectStoreNames.contains('pending_quotations')) {
                    db.createObjectStore('pending_quotations', { autoIncrement: true });
                }
                if (!db.objectStoreNames.contains('pending_config_changes')) {
                    db.createObjectStore('pending_config_changes', { autoIncrement: true });
                }
                if (!db.objectStoreNames.contains('cached_notes')) {
                    db.createObjectStore('cached_notes', { keyPath: 'key' });
                }
            };
            req.onsuccess = function(e) {
                self._db = e.target.result;
                resolve(self._db);
            };
            req.onerror = function(e) {
                reject(e.target.error);
            };
        });
    },

    // ---- Helper: get CSRF token ----
    getCsrfToken: function() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    },

    // ---- Config Cache ----
    cacheConfig: function(configObj) {
        return this.openDB().then(function(db) {
            var tx = db.transaction('cached_config', 'readwrite');
            tx.objectStore('cached_config').put({ key: 'main', data: configObj, timestamp: Date.now() });
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    getCachedConfig: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('cached_config', 'readonly');
            var req = tx.objectStore('cached_config').get('main');
            return new Promise(function(resolve, reject) {
                req.onsuccess = function() {
                    resolve(req.result ? req.result.data : null);
                };
                req.onerror = function() { reject(req.error); };
            });
        });
    },

    // ---- Charges Cache ----
    cacheCharges: function(chargesObj) {
        return this.openDB().then(function(db) {
            var tx = db.transaction('cached_charges', 'readwrite');
            tx.objectStore('cached_charges').put({ key: 'main', data: chargesObj, timestamp: Date.now() });
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    getCachedCharges: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('cached_charges', 'readonly');
            var req = tx.objectStore('cached_charges').get('main');
            return new Promise(function(resolve, reject) {
                req.onsuccess = function() {
                    resolve(req.result ? req.result.data : null);
                };
                req.onerror = function() { reject(req.error); };
            });
        });
    },

    // ---- Notes Cache ----
    cacheNotes: function(notes) {
        return this.openDB().then(function(db) {
            var tx = db.transaction('cached_notes', 'readwrite');
            tx.objectStore('cached_notes').put({ key: 'additional_notes', data: notes, timestamp: Date.now() });
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    getCachedNotes: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('cached_notes', 'readonly');
            var req = tx.objectStore('cached_notes').get('additional_notes');
            return new Promise(function(resolve, reject) {
                req.onsuccess = function() {
                    resolve(req.result ? req.result.data : '');
                };
                req.onerror = function() { reject(req.error); };
            });
        });
    },

    // ---- Pending Quotations Queue ----
    queueQuotation: function(payload) {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_quotations', 'readwrite');
            tx.objectStore('pending_quotations').add({ payload: payload, timestamp: Date.now() });
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    getPendingQuotations: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_quotations', 'readonly');
            var req = tx.objectStore('pending_quotations').getAll();
            return new Promise(function(resolve, reject) {
                req.onsuccess = function() { resolve(req.result || []); };
                req.onerror = function() { reject(req.error); };
            });
        });
    },

    // Get pending quotations WITH their IndexedDB keys (for selective deletion)
    getPendingQuotationsWithKeys: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_quotations', 'readonly');
            var store = tx.objectStore('pending_quotations');
            var items = [];
            return new Promise(function(resolve, reject) {
                var cursor = store.openCursor();
                cursor.onsuccess = function(e) {
                    var c = e.target.result;
                    if (c) {
                        items.push({ key: c.key, payload: c.value.payload, timestamp: c.value.timestamp });
                        c.continue();
                    } else {
                        resolve(items);
                    }
                };
                cursor.onerror = function() { reject(cursor.error); };
            });
        });
    },

    // Delete a single quotation by its IndexedDB key
    deleteQuotationByKey: function(key) {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_quotations', 'readwrite');
            tx.objectStore('pending_quotations').delete(key);
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    clearPendingQuotations: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_quotations', 'readwrite');
            tx.objectStore('pending_quotations').clear();
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    // ---- Pending Config Changes Queue ----
    queueConfigChange: function(payload) {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_config_changes', 'readwrite');
            tx.objectStore('pending_config_changes').add({ payload: payload, timestamp: Date.now() });
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    getPendingConfigChanges: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_config_changes', 'readonly');
            var req = tx.objectStore('pending_config_changes').getAll();
            return new Promise(function(resolve, reject) {
                req.onsuccess = function() { resolve(req.result || []); };
                req.onerror = function() { reject(req.error); };
            });
        });
    },

    clearPendingConfigChanges: function() {
        return this.openDB().then(function(db) {
            var tx = db.transaction('pending_config_changes', 'readwrite');
            tx.objectStore('pending_config_changes').clear();
            return new Promise(function(resolve, reject) {
                tx.oncomplete = resolve;
                tx.onerror = function() { reject(tx.error); };
            });
        });
    },

    // ---- Network Status ----
    setupNetworkListeners: function() {
        var self = this;
        window.addEventListener('online', function() {
            self.updateStatusUI('syncing');
            self.syncAll().then(function(syncResult) {
                if (syncResult && syncResult.failed > 0) {
                    self.updateStatusUI('sync-error');
                } else {
                    self.updateStatusUI('online');
                }
            }).catch(function() {
                self.updateStatusUI('sync-error');
            });
        });
        window.addEventListener('offline', function() {
            self.updateStatusUI('offline');
        });

        // Initial state
        if (!navigator.onLine) {
            self.updateStatusUI('offline');
        }
    },

    updateStatusUI: function(status) {
        var banner = document.getElementById('offlineBanner');
        if (!banner) return;

        if (status === 'offline') {
            banner.className = 'offline-banner show offline';
            banner.innerHTML = '⚠ You are offline / તમે ઑફલાઇન છો';
        } else if (status === 'syncing') {
            banner.className = 'offline-banner show syncing';
            banner.innerHTML = '↻ Syncing data... / ડેટા સિંક થઈ રહ્યો છે...';
        } else if (status === 'sync-error') {
            banner.className = 'offline-banner show offline';
            banner.innerHTML = '⚠ Sync failed — reload page and retry / સિંક નિષ્ફળ — પેજ રિલોડ કરો';
            setTimeout(function() {
                banner.classList.remove('show');
            }, 8000);
        } else {
            banner.className = 'offline-banner show online';
            banner.innerHTML = '✓ Back online / પાછા ઑનલાઇન';
            setTimeout(function() {
                banner.classList.remove('show');
            }, 3000);
        }
    },

    // ---- Sync ----
    syncAll: async function() {
        var self = this;
        var csrfToken = this.getCsrfToken();
        var syncResult = { synced: 0, failed: 0 };

        // 1. Sync pending quotations
        try {
            var pending = await this.getPendingQuotationsWithKeys();
            if (pending.length > 0) {
                var payloads = pending.map(function(item) { return item.payload; });
                var resp = await fetch('/api/sync', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ quotations: payloads })
                });

                if (resp.ok) {
                    var data = await resp.json();
                    var results = data.results || [];

                    // Only delete items that were successfully synced
                    for (var i = 0; i < results.length; i++) {
                        if (results[i].success && pending[i]) {
                            try {
                                await self.deleteQuotationByKey(pending[i].key);
                                syncResult.synced++;
                            } catch(delErr) {
                                console.warn('Failed to delete synced item:', delErr);
                            }
                        } else {
                            syncResult.failed++;
                            console.warn('Quotation sync item failed:', results[i].error || 'Unknown error');
                        }
                    }

                    console.log('Sync complete: ' + syncResult.synced + ' synced, ' + syncResult.failed + ' failed');
                    window.dispatchEvent(new CustomEvent('offlineSync', {
                        detail: {
                            quotationsSynced: syncResult.synced,
                            quotationsFailed: syncResult.failed,
                            results: results
                        }
                    }));
                } else if (resp.status === 419) {
                    // CSRF token expired — page needs reload for fresh token
                    console.warn('Sync failed: CSRF token expired (419). Reload page to retry.');
                    syncResult.failed = pending.length;
                    window.dispatchEvent(new CustomEvent('offlineSyncError', {
                        detail: { error: 'session-expired', message: 'Session expired — please reload page / સેશન સમાપ્ત — પેજ રિલોડ કરો', pending: pending.length }
                    }));
                } else if (resp.status === 401 || resp.status === 302) {
                    // Auth expired — user needs to log in again
                    console.warn('Sync failed: authentication expired (' + resp.status + ').');
                    syncResult.failed = pending.length;
                    window.dispatchEvent(new CustomEvent('offlineSyncError', {
                        detail: { error: 'auth-expired', message: 'Please log in again to sync / સિંક કરવા ફરી લૉગ-ઇન કરો', pending: pending.length }
                    }));
                } else {
                    console.warn('Sync failed with status:', resp.status);
                    syncResult.failed = pending.length;
                    window.dispatchEvent(new CustomEvent('offlineSyncError', {
                        detail: { error: 'server-error', message: 'Sync failed (error ' + resp.status + ') — data is safe, will retry / સિંક નિષ્ફળ — ડેટા સુરક્ષિત છે, ફરી પ્રયાસ થશે', pending: pending.length }
                    }));
                }
            }
        } catch(e) {
            console.warn('Quotation sync failed:', e);
            syncResult.failed = -1; // network error
            window.dispatchEvent(new CustomEvent('offlineSyncError', {
                detail: { error: 'network', message: 'Sync failed — check your connection / સિંક નિષ્ફળ — કનેક્શન તપાસો', pending: 0 }
            }));
        }

        // 2. Sync pending config changes
        try {
            var configChanges = await this.getPendingConfigChanges();
            if (configChanges.length > 0) {
                await this.clearPendingConfigChanges();
            }
        } catch(e) {
            console.warn('Config sync failed:', e);
        }

        return syncResult;
    }
};
