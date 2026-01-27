<?php
$page_title = 'Client Groups';
$page_subtitle = 'Manage client groups';
$show_breadcrumb = true;
$breadcrumb = ' > Client Groups';

require_once __DIR__ . '/config.php';
require_once ROOT_DIR . '/includes/helpers.php';
include ROOT_DIR . '/includes/header.php';
?>

<div class="content">
    <h2>📁 Client Groups</h2>

    <!-- Duplicates Section -->
    <div class="form-container group-section">
        <h3>🔍 Duplicates (Not in Groups)</h3>
        <div id="duplicates-container">Loading...</div>
    </div>

    <!-- Groups Section -->
    <div class="form-container group-section">
        <h3>👥 Existing Groups</h3>
        <div id="groups-container">Loading...</div>
    </div>
    
    <!-- Create Group Section -->
    <div class="form-container group-section">
        <h3>➕ Create New Group</h3>
        <div id="create-group-result"></div>
        <input type="text" id="newGroupName" placeholder="Group name" class="search-input">
        <input type="text" id="groupContactSearch" placeholder="Search contacts..." class="search-input">
        <div id="groupContactSuggestions" class="suggestions-box"></div>
        <div id="selected-members" class="selected-members">
            <small>Selected contacts</small>
        </div>
        <button id="createGroupBtn" class="page-action-btn save" style="width:auto;">✅ Create Group</button>
    </div>
</div>

<div id="deleteConfirmationModal" class="modal-overlay" style="display:none;">
    <div class="modal-content">
        <h3>Confirm</h3>
        <p id="confirm-message">Are you sure?</p>
        <div class="modal-buttons">
            <button id="confirmActionBtn" class="modal-btn confirm-btn">Yes</button>
            <button id="cancelActionBtn" class="modal-btn cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
    let allContacts = [];
    let selectedMembers = [];

    $(document).ready(function () {
        $.ajax({url: '<?= BASE_URL ?>/api/clients.php?action=get', dataType: 'json'})
                .done(res => {
                    if (res.success)
                        allContacts = res.contacts;
                });

        loadDuplicates();
        loadGroups();

        $('#groupContactSearch').on('input', function () {
            const q = $(this).val().trim().toLowerCase();
            if (!q)
                return $('#groupContactSuggestions').hide();
            const filtered = allContacts.filter(c =>
                (c.name?.toLowerCase().includes(q)) ||
                        (c.phone?.toLowerCase().includes(q))
            ).filter(c => !selectedMembers.some(m => m.id == c.id));

            $('#groupContactSuggestions').html(
                    filtered.length ?
                    filtered.map(c => `<div class="suggestion-item" data-id="${c.id}">
                ${c.name}<br><small>${c.phone || ''}</small>
            </div>`).join('') :
                    '<div class="suggestion-item">No contacts</div>'
                    ).show().find('.suggestion-item').on('click', function () {
                const id = $(this).data('id');
                const contact = allContacts.find(c => c.id == id);
                if (contact && !selectedMembers.some(m => m.id == id)) {
                    selectedMembers.push(contact);
                    updateSelected();
                }
                $('#groupContactSearch').val('');
                $('#groupContactSuggestions').hide();
            });
        });
    });

    function updateSelected() {
        $('#selected-members').html(
                selectedMembers.length ?
                selectedMembers.map(m =>
                        `<div class="member-item">
                <span class="member-name">${m.name} (${m.phone || '—'})</span>
                <button onclick="selectedMembers=selectedMembers.filter(x=>x.id!=${m.id});updateSelected()" 
                class="action-btn delete-btn">✕</button>
            </div>`
                ).join('') :
                '<small>Selected contacts</small>'
                );
    }

    function loadDuplicates() {
        $.ajax({url: '<?= BASE_URL ?>/api/groups.php?action=find_duplicates', dataType: 'json'})
                .done(res => {
                    const el = $('#duplicates-container');
                    if (res.success && Object.keys(res.duplicates).length) {
                        let html = '';
                        for (const [phone, contacts] of Object.entries(res.duplicates)) {
                            html += `<div class="duplicate-group">
    <strong>📞 ${phone.replace(/(\d{3})(\d{3})(\d{4})/, '$1 $2 $3')}</strong>
    <button class="action-btn invoice-btn group-from-dupes-btn" 
            data-phone="${phone}" 
            data-ids="[${contacts.map(c => c.id).join(',')}]"
            data-primary="${contacts[0].id}">
        👥 Group
    </button>`;
                            contacts.forEach(c => {
                                html += `<div class="duplicate-group">
                            <strong>${c.name}</strong><br>📞 ${c.phone}
                            <div class="actions-container">
                                <button class="action-btn edit-btn" onclick="location.href='EditContactForm.php?id=${c.id}'">✏️ Edit</button>
                                <button class="action-btn delete-btn" onclick="deleteContact(${c.id})">🗑️ Delete</button>
                                <button class="action-btn keep-btn" onclick="createGroupFromDuplicates([${contacts.map(x => x.id).join(',')}], ${c.id}, '${phone}')">👥 Group</button>
                            </div>
                        </div>`;
                            });
                            html += '</div>';
                        }
                        el.html(html);
                    } else
                        el.html('<p>No duplicates found.</p>');
                })
                .fail(() => $('#duplicates-container').html('<p class="error-message">Failed to load.</p>'));
    }

    function loadGroups() {
        $.ajax({url: '<?= BASE_URL ?>/api/groups.php?action=get_groups', dataType: 'json'})
                .done(res => {
                    const el = $('#groups-container');
                    if (res.success && res.groups.length) {
                        el.html(res.groups.map(g => `
                    <div class="group-header">
                        <strong>📁 ${g.name}</strong>
                        <button class="action-btn delete-btn" onclick="deleteGroup(${g.id})">🗑️ Delete Group</button>
                        <br><small>Primary: ${g.primary_name}</small>
                        <a href="#" class="group-member-link" data-group-id="${g.id}">View Members (${g.member_count})</a>
                        <div id="group-members-${g.id}"></div>
                    </div>
                `).join(''));
                    } else
                        el.html('<p>No groups.</p>');
                });
    }

// 🔹 Load group members
    function loadGroupMembers(groupId) {
        const container = $(`#group-members-${groupId}`);
        $.ajax({
            url: '<?= BASE_URL ?>/api/groups.php?action=get_group_members&group_id=' + groupId,
            dataType: 'json',
            success: function (res) {
                if (res.success && res.members && res.members.length > 0) {
                    const html = res.members.map(m => `
                    <div class="group-member-item">
                        <strong>${m.name}</strong> (${m.phone || '—'})
                        <button class="action-btn delete-btn" onclick="removeMemberFromGroup(${m.id})">➖ Remove</button>
                    </div>
                `).join('');
                    container.html(`<div class="group-members-container">${html}</div>`);
                } else {
                    container.html('<em>No members found.</em>');
                }
            },
            error: function () {
                container.html('<em>Failed to load members.</em>');
            }
        });
    }

// 🔹 Event delegation for "View Members" link
    $(document).on('click', '.group-member-link', function (e) {
        e.preventDefault();
        const groupId = $(this).data('group-id');
        loadGroupMembers(groupId);
    });

    function createGroupFromDuplicates(ids, primary, phone) {
        const name = 'Group ' + phone;
        $.ajax({
            url: '<?= BASE_URL ?>/api/groups.php?action=create_group',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: {contact_ids: ids, group_name: name, primary_id: primary},
            dataType: 'json',
            success: res => {
                if (res.success) {
                    loadGroups();
                    loadDuplicates();
                }
            }
        });
    }

    $(document).on('click', '.group-from-dupes-btn', function () {
        const phone = $(this).data('phone');
        const ids = $(this).data('ids');
        const primary = $(this).data('primary');
        createGroupFromDuplicates(ids, primary, phone);
    });

    function deleteContact(id) {
        if (!confirm('Delete contact?'))
            return;
        $.ajax({
            url: '<?= BASE_URL ?>/api/clients.php?action=delete',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: {id: id},
            dataType: 'json',
            success: res => {
                if (res.success)
                    loadDuplicates();
            }
        });
    }

    function deleteGroup(id) {
        if (!confirm('Delete group? Contacts kept.'))
            return;
        $.ajax({
            url: '<?= BASE_URL ?>/api/groups.php?action=delete_group',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: {group_id: id},
            dataType: 'json',
            success: res => {
                if (res.success) {
                    loadGroups();
                    loadDuplicates();
                }
            }
        });
    }

    function removeMemberFromGroup(contactId) {
        if (!confirm('Remove from group?'))
            return;
        $.ajax({
            url: '<?= BASE_URL ?>/api/groups.php?action=remove_member',
            type: 'POST',
            // ✅ 'data' is explicitly present
            data: {contact_id: contactId},
            dataType: 'json',
            success: res => {
                if (res.success)
                    loadGroups();
            }
        });
    }
</script>

<?php include ROOT_DIR . '/includes/footer.php'; ?>