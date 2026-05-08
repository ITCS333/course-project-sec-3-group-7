/*
  manage_users.js — Admin portal interactivity and user management.
*/

// --- Global Data Store ---
let users = [];

// --- Element Selections ---
const userTableBody      = document.getElementById('user-table-body');
const addUserForm        = document.getElementById('add-user-form');
const changePasswordForm = document.getElementById('password-form');
const searchInput        = document.getElementById('search-input');
const tableHeaders       = document.querySelectorAll('#user-table thead th');

// --- Functions ---

function createUserRow(user) {
  const tr = document.createElement('tr');

  const tdName = document.createElement('td');
  tdName.textContent = user.name;

  const tdEmail = document.createElement('td');
  tdEmail.textContent = user.email;

  const tdAdmin = document.createElement('td');
  const badge = document.createElement('span');
  badge.textContent = user.is_admin === 1 ? 'Yes' : 'No';
  badge.className   = user.is_admin === 1 ? 'badge-yes' : 'badge-no';
  tdAdmin.appendChild(badge);

  const tdActions = document.createElement('td');

  const editBtn = document.createElement('button');
  editBtn.textContent = 'Edit';
  editBtn.className   = 'edit-btn';
  editBtn.dataset.id  = user.id;

  const deleteBtn = document.createElement('button');
  deleteBtn.textContent = 'Delete';
  deleteBtn.className   = 'delete-btn';
  deleteBtn.dataset.id  = user.id;

  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);

  tr.appendChild(tdName);
  tr.appendChild(tdEmail);
  tr.appendChild(tdAdmin);
  tr.appendChild(tdActions);

  return tr;
}

function renderTable(userArray) {
  userTableBody.innerHTML = '';
  userArray.forEach(user => {
    userTableBody.appendChild(createUserRow(user));
  });
}

function handleChangePassword(event) {
  event.preventDefault();

  const currentPasswordInput = document.getElementById('current-password');
  const newPasswordInput     = document.getElementById('new-password');
  const confirmPasswordInput = document.getElementById('confirm-password');

  const currentPassword = currentPasswordInput.value;
  const newPassword     = newPasswordInput.value;
  const confirmPassword = confirmPasswordInput.value;

  if (newPassword !== confirmPassword) {
    alert('Passwords do not match.');
    return;
  }
  if (newPassword.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  // Clear fields synchronously right after validation
  currentPasswordInput.value = '';
  newPasswordInput.value     = '';
  confirmPasswordInput.value = '';

  const userId = window._currentUserId || null;

  fetch('../api/index.php?action=change_password', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id: userId, current_password: currentPassword, new_password: newPassword })
  })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        alert('Password updated successfully!');
      } else {
        alert(data.message || 'Failed to update password.');
      }
    })
    .catch(() => alert('Network error. Please try again.'));
}

function handleAddUser(event) {
  event.preventDefault();

  const name     = document.getElementById('user-name').value.trim();
  const email    = document.getElementById('user-email').value.trim();
  const password = document.getElementById('default-password').value.trim();
  const isAdmin  = document.getElementById('is-admin').value;

  if (!name || !email || !password) {
    alert('Please fill out all required fields.');
    return;
  }
  if (password.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  fetch('../api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name, email, password, is_admin: parseInt(isAdmin) })
  })
    .then(res => {
      if (res.status === 201) {
        addUserForm.reset();
        return loadUsersAndInitialize();
      }
      return res.json().then(d => { alert(d.message || 'Failed to add user.'); });
    })
    .catch(() => alert('Network error. Please try again.'));
}

function handleTableClick(event) {
  const target = event.target;

  if (target.classList.contains('delete-btn')) {
    const id = target.dataset.id;
    if (!confirm('Delete this user?')) return;

    fetch('../api/index.php?id=' + id, { method: 'DELETE' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          users = users.filter(u => String(u.id) !== String(id));
          renderTable(users);
        } else {
          alert(data.message || 'Failed to delete user.');
        }
      })
      .catch(() => alert('Network error. Please try again.'));
  }

  if (target.classList.contains('edit-btn')) {
    const id   = target.dataset.id;
    const user = users.find(u => String(u.id) === String(id));
    if (!user) return;

    const newName  = prompt('New name:', user.name);
    if (newName === null) return;
    const newEmail = prompt('New email:', user.email);
    if (newEmail === null) return;
    const newAdmin = prompt('Is admin? (0 = No, 1 = Yes):', user.is_admin);
    if (newAdmin === null) return;

    fetch('../api/index.php', {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: parseInt(id), name: newName, email: newEmail, is_admin: parseInt(newAdmin) })
    })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          loadUsersAndInitialize();
        } else {
          alert(data.message || 'Failed to update user.');
        }
      })
      .catch(() => alert('Network error. Please try again.'));
  }
}

function handleSearch(event) {
  const term = searchInput.value.toLowerCase();
  if (!term) {
    renderTable(users);
    return;
  }
  const filtered = users.filter(u =>
    u.name.toLowerCase().includes(term) || u.email.toLowerCase().includes(term)
  );
  renderTable(filtered);
}

function handleSort(event) {
  const colIndex = event.currentTarget.cellIndex;
  const colMap   = { 0: 'name', 1: 'email', 2: 'is_admin' };
  const field    = colMap[colIndex];
  if (!field) return;

  const th  = event.currentTarget;
  const dir = th.dataset.sortDir === 'asc' ? 'desc' : 'asc';
  th.dataset.sortDir = dir;

  users.sort((a, b) => {
    if (field === 'is_admin') {
      return dir === 'asc' ? a[field] - b[field] : b[field] - a[field];
    }
    const cmp = String(a[field]).localeCompare(String(b[field]));
    return dir === 'asc' ? cmp : -cmp;
  });

  renderTable(users);
}

let _listenersAttached = false;

async function loadUsersAndInitialize() {
  try {
    const res = await fetch('../api/index.php');
    if (!res.ok) {
      console.error('Failed to load users:', res.status);
      alert('Failed to load users.');
      return;
    }
    const json = await res.json();
    users = json.data || [];
    renderTable(users);

    if (!_listenersAttached) {
      _listenersAttached = true;
      changePasswordForm.addEventListener('submit', handleChangePassword);
      addUserForm.addEventListener('submit', handleAddUser);
      userTableBody.addEventListener('click', handleTableClick);
      searchInput.addEventListener('input', handleSearch);
      tableHeaders.forEach(th => th.addEventListener('click', handleSort));
    }
  } catch (err) {
    console.error(err);
    alert('Error loading users.');
  }
}

// --- Initial Page Load ---
loadUsersAndInitialize();