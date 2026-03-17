<?php
/**
 * Admin panel template (content only — rendered inside layout.php)
 * Expects: $users (array), $bands (array), $user (array), $myBands (array)
 */
$usersJson = json_encode(array_values($users ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$bandsJson = json_encode(array_values($bands ?? []), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$selfId    = e($user['id'] ?? '');
?>

<div x-data="adminApp()" x-init="init()" x-cloak>

    <div class="page-header">
        <h1>⚙️ Admin Panel</h1>
    </div>

    <div class="page-body">

        <!-- ===========================
             Users section
             =========================== -->
        <div class="card mb-4" style="margin-bottom:24px;">
            <div class="card-header">
                <h2>Users</h2>
                <button class="btn btn-primary btn-sm" @click="openUserCreate()">+ Add user</button>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Bands</th>
                            <th>Role</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="u in users" :key="u.id">
                            <tr>
                                <td x-text="u.name"></td>
                                <td x-text="u.email"></td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <template x-for="bid in (u.bands || [])" :key="bid">
                                            <span class="badge"
                                                  x-text="bandName(bid)"
                                                  style="background:var(--primary-light);color:var(--primary);"></span>
                                        </template>
                                        <template x-if="!u.bands || u.bands.length === 0">
                                            <span class="text-muted text-xs">—</span>
                                        </template>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" :class="u.is_admin ? 'badge-admin' : ''"
                                          x-text="u.is_admin ? 'Admin' : 'Member'"></span>
                                </td>
                                <td>
                                    <div style="display:flex;gap:6px;">
                                        <button class="btn btn-secondary btn-sm"
                                                @click="openUserEdit(u)">Edit</button>
                                        <button class="btn btn-danger btn-sm"
                                                :disabled="u.id === selfId"
                                                x-show="u.id !== selfId"
                                                @click="deleteUser(u.id)">Delete</button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="users.length === 0">
                            <tr><td colspan="5" class="text-muted text-sm" style="text-align:center;padding:20px;">No users yet.</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ===========================
             Bands section
             =========================== -->
        <div class="card">
            <div class="card-header">
                <h2>Bands</h2>
                <button class="btn btn-primary btn-sm" @click="openBandCreate()">+ Add band</button>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Members</th>
                            <th>Files</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="b in bands" :key="b.id">
                            <tr>
                                <td>
                                    <a :href="'/band/' + b.id" x-text="b.name"></a>
                                </td>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:4px;">
                                        <template x-for="uid in (b.members || [])" :key="uid">
                                            <span class="badge"
                                                  x-text="userName(uid)"
                                                  style="background:var(--bg);color:var(--text);border:1px solid var(--border);"></span>
                                        </template>
                                        <template x-if="!b.members || b.members.length === 0">
                                            <span class="text-muted text-xs">—</span>
                                        </template>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                                        <template x-if="b.epk_file">
                                            <span class="badge" style="background:#FEF3C7;color:#92400E;">EPK</span>
                                        </template>
                                        <template x-if="b.dossier_file">
                                            <span class="badge" style="background:#ECFDF5;color:#065F46;">Dossier</span>
                                        </template>
                                        <template x-if="!b.epk_file && !b.dossier_file">
                                            <span class="text-muted text-xs">—</span>
                                        </template>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-danger btn-sm"
                                            @click="deleteBand(b.id)">Delete</button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="bands.length === 0">
                            <tr><td colspan="4" class="text-muted text-sm" style="text-align:center;padding:20px;">No bands yet.</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- ===========================
         User modal (create/edit)
         =========================== -->
    <div class="modal-overlay" x-show="userModalOpen" x-cloak @click.self="userModalOpen = false" style="display:none;">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <h2 x-text="editingUser ? 'Edit User' : 'New User'"></h2>
                <button class="modal-close" @click="userModalOpen = false">✕</button>
            </div>
            <div class="modal-body">
                <div x-show="userMsg" class="alert" :class="userMsgClass" x-text="userMsg"
                     style="margin-bottom:12px;"></div>

                <div class="form-group">
                    <label class="form-label">Full name</label>
                    <input type="text" class="form-control" x-model="userForm.name" placeholder="Jane Smith">
                </div>
                <div class="form-group">
                    <label class="form-label">Email address</label>
                    <input type="email" class="form-control" x-model="userForm.email" placeholder="jane@example.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Bands</label>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-top:4px;">
                        <template x-for="b in bands" :key="b.id">
                            <label class="form-check">
                                <input type="checkbox" :value="b.id"
                                       :checked="userForm.bands.includes(b.id)"
                                       @change="toggleBandCheck(b.id, $event.target.checked)">
                                <span x-text="b.name"></span>
                            </label>
                        </template>
                        <template x-if="bands.length === 0">
                            <span class="text-muted text-sm">No bands exist yet. Create one first.</span>
                        </template>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-check">
                        <input type="checkbox" x-model="userForm.is_admin">
                        <span>Admin</span>
                    </label>
                    <p class="form-hint">Admins can manage all bands and users.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost btn-sm" @click="userModalOpen = false">Cancel</button>
                <button class="btn btn-primary btn-sm" @click="saveUser()" :disabled="userSaving">
                    <span x-text="userSaving ? 'Saving…' : 'Save user'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===========================
         Band modal (create only)
         =========================== -->
    <div class="modal-overlay" x-show="bandModalOpen" x-cloak @click.self="bandModalOpen = false" style="display:none;">
        <div class="modal" @click.stop>
            <div class="modal-header">
                <h2>New Band</h2>
                <button class="modal-close" @click="bandModalOpen = false">✕</button>
            </div>
            <div class="modal-body">
                <div x-show="bandMsg" class="alert" :class="bandMsgClass" x-text="bandMsg"
                     style="margin-bottom:12px;"></div>

                <div class="form-group">
                    <label class="form-label">Band name</label>
                    <input type="text" class="form-control" x-model="bandForm.name" placeholder="The Fantastic Five">
                </div>
                <div class="form-group">
                    <label class="form-label">Members</label>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-top:4px;">
                        <template x-for="u in users" :key="u.id">
                            <label class="form-check">
                                <input type="checkbox" :value="u.id"
                                       :checked="bandForm.members.includes(u.id)"
                                       @change="toggleMemberCheck(u.id, $event.target.checked)">
                                <span x-text="u.name + ' (' + u.email + ')'"></span>
                            </label>
                        </template>
                        <template x-if="users.length === 0">
                            <span class="text-muted text-sm">No users exist yet.</span>
                        </template>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-ghost btn-sm" @click="bandModalOpen = false">Cancel</button>
                <button class="btn btn-primary btn-sm" @click="saveBand()" :disabled="bandSaving">
                    <span x-text="bandSaving ? 'Saving…' : 'Create band'"></span>
                </button>
            </div>
        </div>
    </div>

</div>

<script>
function adminApp() {
    return {
        users:         <?= $usersJson ?>,
        bands:         <?= $bandsJson ?>,
        selfId:        '<?= $selfId ?>',

        // User modal
        userModalOpen: false,
        editingUser:   null,
        userSaving:    false,
        userMsg:       '',
        userMsgClass:  'alert-success',
        userForm: { name: '', email: '', bands: [], is_admin: false },

        // Band modal
        bandModalOpen: false,
        bandSaving:    false,
        bandMsg:       '',
        bandMsgClass:  'alert-success',
        bandForm: { name: '', members: [] },

        init() {},

        bandName(id) {
            const b = this.bands.find(x => x.id === id);
            return b ? b.name : id;
        },

        userName(id) {
            const u = this.users.find(x => x.id === id);
            return u ? u.name : id;
        },

        // ---- User actions ----
        openUserCreate() {
            this.editingUser  = null;
            this.userForm     = { name: '', email: '', bands: [], is_admin: false };
            this.userMsg      = '';
            this.userModalOpen = true;
        },

        openUserEdit(u) {
            this.editingUser  = u;
            this.userForm     = { name: u.name, email: u.email, bands: [...(u.bands || [])], is_admin: !!u.is_admin };
            this.userMsg      = '';
            this.userModalOpen = true;
        },

        toggleBandCheck(bandId, checked) {
            if (checked) {
                if (!this.userForm.bands.includes(bandId)) this.userForm.bands.push(bandId);
            } else {
                this.userForm.bands = this.userForm.bands.filter(id => id !== bandId);
            }
        },

        async saveUser() {
            this.userMsg   = '';
            this.userSaving = true;
            const payload  = { ...this.userForm };
            if (this.editingUser) payload.id = this.editingUser.id;

            try {
                const res  = await fetch('/api/admin/users', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.userMsg      = data.error || 'Failed to save user.';
                    this.userMsgClass = 'alert-danger';
                    return;
                }
                location.reload();
            } catch {
                this.userMsg      = 'Network error.';
                this.userMsgClass = 'alert-danger';
            } finally {
                this.userSaving = false;
            }
        },

        async deleteUser(id) {
            if (!confirm('Delete this user? This cannot be undone.')) return;
            try {
                await fetch(`/api/admin/users/${id}/delete`, { method: 'POST' });
                location.reload();
            } catch {
                alert('Failed to delete user.');
            }
        },

        // ---- Band actions ----
        openBandCreate() {
            this.bandForm     = { name: '', members: [] };
            this.bandMsg      = '';
            this.bandModalOpen = true;
        },

        toggleMemberCheck(userId, checked) {
            if (checked) {
                if (!this.bandForm.members.includes(userId)) this.bandForm.members.push(userId);
            } else {
                this.bandForm.members = this.bandForm.members.filter(id => id !== userId);
            }
        },

        async saveBand() {
            this.bandMsg    = '';
            this.bandSaving = true;
            try {
                const res  = await fetch('/api/admin/bands', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(this.bandForm),
                });
                const data = await res.json();
                if (!res.ok) {
                    this.bandMsg      = data.error || 'Failed to create band.';
                    this.bandMsgClass = 'alert-danger';
                    return;
                }
                location.reload();
            } catch {
                this.bandMsg      = 'Network error.';
                this.bandMsgClass = 'alert-danger';
            } finally {
                this.bandSaving = false;
            }
        },

        async deleteBand(id) {
            if (!confirm('Delete this band? All events will remain but lose their band reference.')) return;
            try {
                await fetch(`/api/admin/bands/${id}/delete`, { method: 'POST' });
                location.reload();
            } catch {
                alert('Failed to delete band.');
            }
        },
    };
}
</script>
