<?php
/**
 * Band profile template (content only — rendered inside layout.php)
 * Expects: $band (array), $members (array), $user (array), $myBands (array)
 */
$bandJson    = json_encode($band, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$bandId      = e($band['id'] ?? '');
$isAdmin     = !empty($user['is_admin']);
$isMember    = in_array($band['id'] ?? '', array_column($myBands ?? [], 'id'), true);
$canEdit     = $isAdmin || $isMember;

$epkFile     = $band['epk_file'] ?? null;
$dossierFile = $band['dossier_file'] ?? null;
?>

<div x-data="bandApp()" x-init="init()" x-cloak>

    <div class="page-header">
        <h1>🎸 <span x-text="band.name"></span></h1>
        <?php if ($canEdit): ?>
        <button class="btn btn-secondary btn-sm" @click="editing = !editing"
                x-text="editing ? 'Cancel' : 'Edit band'"></button>
        <?php endif; ?>
    </div>

    <div class="page-body">
        <div class="two-col">

            <!-- LEFT: Band info card -->
            <div>
                <div class="card mb-4" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3>Band Info</h3>
                        <template x-if="editing">
                            <button class="btn btn-primary btn-sm" @click="saveBand()" :disabled="saving">
                                <span x-text="saving ? 'Saving…' : 'Save'"></span>
                            </button>
                        </template>
                    </div>
                    <div class="card-body">

                        <div x-show="saveMsg" class="alert" :class="saveMsgClass" x-text="saveMsg"
                             style="margin-bottom:12px;"></div>

                        <!-- View mode -->
                        <template x-if="!editing">
                            <dl style="display:flex;flex-direction:column;gap:10px;">
                                <div>
                                    <dt class="section-title">Band name</dt>
                                    <dd x-text="band.name"></dd>
                                </div>
                                <template x-if="band.website">
                                    <div>
                                        <dt class="section-title">Website</dt>
                                        <dd><a :href="band.website" target="_blank" rel="noopener"
                                               x-text="band.website"></a></dd>
                                    </div>
                                </template>
                                <template x-if="band.instagram">
                                    <div>
                                        <dt class="section-title">Instagram</dt>
                                        <dd x-text="band.instagram"></dd>
                                    </div>
                                </template>
                                <template x-if="band.youtube">
                                    <div>
                                        <dt class="section-title">YouTube</dt>
                                        <dd><a :href="band.youtube" target="_blank" rel="noopener"
                                               x-text="band.youtube"></a></dd>
                                    </div>
                                </template>
                                <template x-if="band.facebook">
                                    <div>
                                        <dt class="section-title">Facebook</dt>
                                        <dd><a :href="band.facebook" target="_blank" rel="noopener"
                                               x-text="band.facebook"></a></dd>
                                    </div>
                                </template>
                                <template x-if="!band.website && !band.instagram && !band.youtube && !band.facebook">
                                    <div class="text-muted text-sm">No social links added yet.</div>
                                </template>
                            </dl>
                        </template>

                        <!-- Edit mode -->
                        <template x-if="editing">
                            <div>
                                <div class="form-group">
                                    <label class="form-label">Band name</label>
                                    <input type="text" class="form-control" x-model="form.name">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Website</label>
                                    <input type="url" class="form-control" x-model="form.website"
                                           placeholder="https://yourband.com">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Instagram</label>
                                    <input type="text" class="form-control" x-model="form.instagram"
                                           placeholder="@yourband">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">YouTube</label>
                                    <input type="url" class="form-control" x-model="form.youtube"
                                           placeholder="https://youtube.com/...">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Facebook</label>
                                    <input type="url" class="form-control" x-model="form.facebook"
                                           placeholder="https://facebook.com/...">
                                </div>
                            </div>
                        </template>

                    </div>
                </div>

                <!-- EPK upload card -->
                <div class="card">
                    <div class="card-header">
                        <h3>EPK (Press Kit)</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($epkFile): ?>
                        <a href="/uploads/<?= e($epkFile) ?>" target="_blank"
                           class="btn btn-secondary btn-sm mb-4" style="margin-bottom:12px;">
                            📄 View EPK
                        </a>
                        <?php endif; ?>

                        <?php if ($canEdit): ?>
                        <div x-show="epkMsg" class="alert" :class="epkMsgClass" x-text="epkMsg"
                             style="margin-bottom:10px;"></div>
                        <div class="upload-zone"
                             @dragover.prevent="$el.classList.add('drag-over')"
                             @dragleave="$el.classList.remove('drag-over')"
                             @drop.prevent="$el.classList.remove('drag-over'); uploadFile($event.dataTransfer.files[0], 'epk')">
                            <input type="file" accept=".pdf,application/pdf"
                                   @change="uploadFile($event.target.files[0], 'epk'); $event.target.value=''">
                            <span class="upload-zone-icon">📁</span>
                            <div class="upload-zone-text">Drop PDF here or click to browse</div>
                            <div class="upload-zone-hint">PDF only · max 20 MB</div>
                        </div>
                        <?php else: ?>
                            <?php if (!$epkFile): ?>
                            <p class="text-muted text-sm">No EPK uploaded yet.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- RIGHT: Members + Dossier -->
            <div>
                <!-- Members card -->
                <div class="card mb-4" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3>Members</h3>
                    </div>
                    <div class="card-body">
                        <?php if (empty($members)): ?>
                        <p class="text-muted text-sm">No members assigned.</p>
                        <?php else: ?>
                        <div class="member-chips">
                            <?php foreach ($members as $member): ?>
                            <?php
                                $initials = strtoupper(implode('', array_map(
                                    fn($p) => $p[0] ?? '',
                                    array_slice(explode(' ', $member['name'] ?? 'U'), 0, 2)
                                )));
                            ?>
                            <div class="member-chip">
                                <div class="avatar"><?= e($initials) ?></div>
                                <span><?= e($member['name'] ?? 'Unknown') ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Dossier upload card -->
                <div class="card">
                    <div class="card-header">
                        <h3>Dossier</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($dossierFile): ?>
                        <a href="/uploads/<?= e($dossierFile) ?>" target="_blank"
                           class="btn btn-secondary btn-sm mb-4" style="margin-bottom:12px;">
                            📄 View Dossier
                        </a>
                        <?php endif; ?>

                        <?php if ($canEdit): ?>
                        <div x-show="dossierMsg" class="alert" :class="dossierMsgClass" x-text="dossierMsg"
                             style="margin-bottom:10px;"></div>
                        <div class="upload-zone"
                             @dragover.prevent="$el.classList.add('drag-over')"
                             @dragleave="$el.classList.remove('drag-over')"
                             @drop.prevent="$el.classList.remove('drag-over'); uploadFile($event.dataTransfer.files[0], 'dossier')">
                            <input type="file" accept=".pdf,application/pdf"
                                   @change="uploadFile($event.target.files[0], 'dossier'); $event.target.value=''">
                            <span class="upload-zone-icon">📋</span>
                            <div class="upload-zone-text">Drop PDF here or click to browse</div>
                            <div class="upload-zone-hint">PDF only · max 20 MB</div>
                        </div>
                        <?php else: ?>
                            <?php if (!$dossierFile): ?>
                            <p class="text-muted text-sm">No dossier uploaded yet.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<script>
function bandApp() {
    const initialBand = <?= $bandJson ?>;
    return {
        band:        Object.assign({}, initialBand),
        form:        Object.assign({}, initialBand),
        editing:     false,
        saving:      false,
        saveMsg:     '',
        saveMsgClass:'alert-success',
        epkMsg:      '',
        epkMsgClass: 'alert-success',
        dossierMsg:  '',
        dossierMsgClass: 'alert-success',

        init() {
            // nothing async needed on mount
        },

        async saveBand() {
            this.saving  = true;
            this.saveMsg = '';
            try {
                const res = await fetch(`/api/bands/${this.band.id}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name:      this.form.name,
                        website:   this.form.website,
                        instagram: this.form.instagram,
                        youtube:   this.form.youtube,
                        facebook:  this.form.facebook,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.saveMsg     = data.error || 'Save failed.';
                    this.saveMsgClass = 'alert-danger';
                    return;
                }
                // Update reactive band object
                Object.assign(this.band, this.form);
                this.editing     = false;
                this.saveMsg     = '✅ Band info saved.';
                this.saveMsgClass = 'alert-success';
                setTimeout(() => { this.saveMsg = ''; }, 3000);
            } catch {
                this.saveMsg     = 'Network error.';
                this.saveMsgClass = 'alert-danger';
            } finally {
                this.saving = false;
            }
        },

        async uploadFile(file, type) {
            if (!file) return;
            const msgKey    = type === 'epk' ? 'epkMsg' : 'dossierMsg';
            const classKey  = type === 'epk' ? 'epkMsgClass' : 'dossierMsgClass';
            this[msgKey]    = 'Uploading…';
            this[classKey]  = 'alert-info';

            const fd = new FormData();
            fd.append('file', file);
            try {
                const res = await fetch(`/api/bands/${this.band.id}/upload/${type}`, {
                    method: 'POST',
                    body: fd,
                });
                const data = await res.json();
                if (!res.ok) {
                    this[msgKey]   = data.error || 'Upload failed.';
                    this[classKey] = 'alert-danger';
                    return;
                }
                this[msgKey]   = '✅ File uploaded successfully.';
                this[classKey] = 'alert-success';
                // Update local band object so "View" link appears without reload
                if (type === 'epk')     this.band.epk_file     = data.path;
                if (type === 'dossier') this.band.dossier_file = data.path;
                setTimeout(() => { this[msgKey] = ''; }, 4000);
            } catch {
                this[msgKey]   = 'Network error during upload.';
                this[classKey] = 'alert-danger';
            }
        },
    };
}
</script>
