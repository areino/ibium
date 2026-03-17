<?php
/**
 * Calendar template (content only — rendered inside layout.php)
 * Expects: $user (array), $myBands (array), $userBands (array)
 */
$bandsJson = json_encode(array_values($myBands ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$isAdmin   = !empty($user['is_admin']) ? 'true' : 'false';
$userId    = e($user['id'] ?? '');
?>

<!-- FullCalendar CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<div x-data="calendarApp()" x-init="init()" x-cloak>

    <!-- Page header -->
    <div class="page-header">
        <h1>📅 Calendar</h1>
        <button class="btn btn-primary" @click="openCreate(null)">+ Add event</button>
    </div>

    <div class="page-body">

        <!-- Band filter chips (only if in 2+ bands) -->
        <?php if (count($myBands ?? []) >= 2): ?>
        <div class="chips mb-4" style="margin-bottom:16px;">
            <span class="text-sm text-muted" style="margin-right:4px;">Filter:</span>
            <button class="chip" :class="{ active: bandFilter === '' }" @click="setFilter('')">
                All
            </button>
            <?php foreach ($myBands as $band): ?>
            <button class="chip"
                    :class="{ active: bandFilter === '<?= e($band['id']) ?>' }"
                    @click="setFilter('<?= e($band['id']) ?>')">
                <?= e($band['name']) ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Legend -->
        <div class="legend mb-4" style="margin-bottom:16px;">
            <div class="legend-item">
                <div class="legend-dot" style="background:var(--gig);"></div>
                <span>Gig</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot" style="background:var(--practice);"></div>
                <span>Practice</span>
            </div>
            <div class="legend-item">
                <div class="legend-dot" style="background:var(--block);"></div>
                <span>Personal block</span>
            </div>
        </div>

        <!-- Calendar mount -->
        <div class="card" style="padding:16px;">
            <div id="calendar"></div>
        </div>

    </div>

    <!-- Event modal -->
    <div class="modal-overlay" x-show="modalOpen" x-cloak @click.self="close()" style="display:none;">
        <div class="modal" @click.stop>

            <!-- View mode -->
            <template x-if="modalMode === 'view'">
                <div>
                    <div class="modal-header">
                        <h2 x-text="viewEvent.title || 'Event'"></h2>
                        <button class="modal-close" @click="close()">✕</button>
                    </div>
                    <div class="modal-body">
                        <ul class="event-detail-list">
                            <li>
                                <span class="detail-icon">🗓️</span>
                                <span x-text="fmtDate(viewEvent.start)"></span>
                                <template x-if="viewEvent.end">
                                    <span>&nbsp;→ <span x-text="fmtDate(viewEvent.end)"></span></span>
                                </template>
                            </li>
                            <li x-show="viewEvent.extendedProps?.band_name">
                                <span class="detail-icon">🎸</span>
                                <span x-text="viewEvent.extendedProps?.band_name"></span>
                            </li>
                            <li x-show="viewEvent.extendedProps?.user_name">
                                <span class="detail-icon">👤</span>
                                <span x-text="viewEvent.extendedProps?.user_name"></span>
                            </li>
                            <li x-show="viewEvent.extendedProps?.location">
                                <span class="detail-icon">📍</span>
                                <span x-text="viewEvent.extendedProps?.location"></span>
                            </li>
                            <li x-show="viewEvent.extendedProps?.comments">
                                <span class="detail-icon">💬</span>
                                <span x-text="viewEvent.extendedProps?.comments"></span>
                            </li>
                        </ul>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-ghost btn-sm" @click="close()">Close</button>
                        <template x-if="viewEvent.extendedProps?.is_own || <?= $isAdmin ?>">
                            <div style="display:flex;gap:8px;">
                                <button class="btn btn-secondary btn-sm" @click="startEdit()">Edit</button>
                                <button class="btn btn-danger btn-sm" @click="deleteEvent()">Delete</button>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Create / Edit mode -->
            <template x-if="modalMode === 'create' || modalMode === 'edit'">
                <div>
                    <div class="modal-header">
                        <h2 x-text="modalMode === 'edit' ? 'Edit Event' : 'New Event'"></h2>
                        <button class="modal-close" @click="close()">✕</button>
                    </div>
                    <div class="modal-body">

                        <div class="form-group">
                            <label class="form-label">Event type</label>
                            <select class="form-control" x-model="form.type" @change="onTypeChange()">
                                <option value="gig">🎸 Gig</option>
                                <option value="practice">🥁 Practice</option>
                                <option value="block">🚫 Personal block</option>
                            </select>
                        </div>

                        <div class="form-group" x-show="form.type !== 'block'">
                            <label class="form-label">Band</label>
                            <select class="form-control" x-model="form.band_id">
                                <option value="">— Select band —</option>
                                <?php foreach ($myBands as $band): ?>
                                <option value="<?= e($band['id']) ?>"><?= e($band['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Start</label>
                                <input type="datetime-local" class="form-control" x-model="form.datetime">
                            </div>
                            <div class="form-group">
                                <label class="form-label">End <span class="text-muted">(optional)</span></label>
                                <input type="datetime-local" class="form-control" x-model="form.datetime_end">
                            </div>
                        </div>

                        <div class="form-group" x-show="form.type !== 'block'">
                            <label class="form-label">Location <span class="text-muted">(optional)</span></label>
                            <input type="text" class="form-control" x-model="form.location"
                                   placeholder="Venue name, city…">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control" rows="3" x-model="form.comments"
                                      placeholder="Load-in time, set list, etc."></textarea>
                        </div>

                        <div x-show="formError" class="alert alert-danger mb-0" x-text="formError"></div>

                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-ghost btn-sm" @click="close()">Cancel</button>
                        <button class="btn btn-primary btn-sm" @click="save()" :disabled="saving">
                            <span x-text="saving ? 'Saving…' : 'Save event'"></span>
                        </button>
                    </div>
                </div>
            </template>

        </div>
    </div>

</div>

<!-- FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
function calendarApp() {
    return {
        _cal: null,
        modalOpen: false,
        modalMode: 'create',   // 'create' | 'edit' | 'view'
        viewEvent: {},
        bandFilter: '',
        saving: false,
        formError: '',
        form: {
            id: null,
            type: 'gig',
            band_id: '',
            datetime: '',
            datetime_end: '',
            location: '',
            comments: '',
        },

        init() {
            const self = this;
            const calEl = document.getElementById('calendar');
            this._cal = new FullCalendar.Calendar(calEl, {
                initialView: window.innerWidth < 640 ? 'listWeek' : 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,listWeek'
                },
                height: 'auto',
                nowIndicator: true,
                firstDay: 1,
                events(fetchInfo, successCallback, failureCallback) {
                    const params = new URLSearchParams({
                        start: fetchInfo.startStr,
                        end: fetchInfo.endStr,
                    });
                    if (self.bandFilter) {
                        params.set('band_id', self.bandFilter);
                    }
                    fetch('/api/events?' + params.toString())
                        .then(r => r.json())
                        .then(successCallback)
                        .catch(failureCallback);
                },
                dateClick(info) {
                    self.openCreate(info.dateStr);
                },
                eventClick(info) {
                    self.openView(info.event);
                },
            });
            this._cal.render();
        },

        openCreate(dt) {
            this.form = {
                id: null,
                type: 'gig',
                band_id: '',
                datetime: dt ? dt.substring(0, 16) : '',
                datetime_end: '',
                location: '',
                comments: '',
            };
            this.formError = '';
            this.modalMode = 'create';
            this.modalOpen = true;
        },

        openView(event) {
            this.viewEvent = {
                id: event.id,
                title: event.title,
                start: event.startStr,
                end: event.endStr || null,
                extendedProps: event.extendedProps || {},
            };
            this.modalMode = 'view';
            this.modalOpen = true;
        },

        startEdit() {
            const ep = this.viewEvent.extendedProps || {};
            this.form = {
                id: this.viewEvent.id,
                type: ep.type || 'gig',
                band_id: ep.band_id || '',
                datetime: this.viewEvent.start ? this.viewEvent.start.substring(0, 16) : '',
                datetime_end: this.viewEvent.end ? this.viewEvent.end.substring(0, 16) : '',
                location: ep.location || '',
                comments: ep.comments || '',
            };
            this.formError = '';
            this.modalMode = 'edit';
        },

        onTypeChange() {
            if (this.form.type === 'block') {
                this.form.band_id = '';
            }
        },

        async save() {
            this.formError = '';
            if (!this.form.datetime) {
                this.formError = 'Start date/time is required.';
                return;
            }
            if (this.form.type !== 'block' && !this.form.band_id) {
                this.formError = 'Please select a band.';
                return;
            }

            this.saving = true;
            const url = this.form.id ? `/api/events/${this.form.id}` : '/api/events';
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.form),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.formError = data.error || 'Failed to save event.';
                    return;
                }
                this.close();
                this._cal.refetchEvents();
            } catch (err) {
                this.formError = 'Network error. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async deleteEvent() {
            if (!confirm('Delete this event?')) return;
            try {
                await fetch(`/api/events/${this.viewEvent.id}/delete`, { method: 'POST' });
                this.close();
                this._cal.refetchEvents();
            } catch (err) {
                alert('Failed to delete event.');
            }
        },

        setFilter(bandId) {
            this.bandFilter = bandId;
            this._cal.refetchEvents();
        },

        close() {
            this.modalOpen = false;
            this.formError = '';
            this.saving = false;
        },

        fmtDate(iso) {
            if (!iso) return '';
            try {
                return new Date(iso).toLocaleString(undefined, {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            } catch {
                return iso;
            }
        },
    };
}
</script>
