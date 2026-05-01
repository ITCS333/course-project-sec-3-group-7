/*
  Requirement: Make the "Manage Assignments" page interactive.

  Instructions:
  1. This file is already linked to `admin.html` via:
         <script src="admin.js" defer></script>

  2. In `admin.html`:
     - The form has id="assignment-form".
     - The submit button has id="add-assignment".
     - The <tbody> has id="assignments-tbody".
     - Columns rendered per row:
       Title | Due Date | Description | Actions.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...assignment objects ] }
  Each assignment object shape:
    {
      id:          number,   // integer primary key from the assignments table
      title:       string,
      due_date:    string,   // "YYYY-MM-DD" — matches the SQL column name
      description: string,
      files:       string[]  // decoded array of URL strings
    }
*/

// --- Global Data Store ---
// Holds the assignments currently displayed in the table.
let assignments = [];

// --- Element Selections ---
// TODO: Select the assignment form by id 'assignment-form'.
const form = document.getElementById('assignment-form');

// TODO: Select the assignments table body by id 'assignments-tbody'.
const tbody = document.getElementById('assignments-tbody');

const toggleFormBtn = document.getElementById('toggle-form-btn');
const formSection = document.getElementById('form-section');

// --- Functions ---

/**
 * TODO: Implement createAssignmentRow.
 *
 * Parameters:
 *   assignment — one assignment object with shape:
 *     { id, title, due_date, description, files }
 *
 * Returns a <tr> element with four <td>s:
 *   1. title
 *   2. due_date   (the "YYYY-MM-DD" string — use due_date, not dueDate)
 *   3. description
 *   4. Actions — two buttons:
 *        <button class="edit-btn"   data-id="{id}">Edit</button>
 *        <button class="delete-btn" data-id="{id}">Delete</button>
 *      The data-id holds the integer primary key from the assignments table.
 */
function createAssignmentRow(assignment) {
  // ... your implementation here ...
    const tr = document.createElement('tr');

  tr.innerHTML = `
    <td>${assignment.title}</td>
    <td>${assignment.due_date}</td>
    <td>${assignment.description}</td>
  <td>
  <div class="actions">
    <button class="edit-btn" data-id="${assignment.id}">✏️ Edit</button>
    <button class="delete-btn" data-id="${assignment.id}">🗑️ Delete</button>
  </div>
</td>
  `;

  return tr;
}

/**
 * TODO: Implement renderTable.
 *
 * It should:
 * 1. Clear the assignments table body (set innerHTML to "").
 * 2. Loop through the global `assignments` array.
 * 3. For each assignment, call createAssignmentRow(assignment) and
 *    append the <tr> to the table body.
 */
function renderTable() {
  // ... your implementation here ...
    tbody.innerHTML = "";

  assignments.forEach(a => {
    const row = createAssignmentRow(a);
    tbody.appendChild(row);
  });
}

/**
 * TODO: Implement handleAddAssignment (async).
 *
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Call event.preventDefault().
 * 2. Read values from:
 *      - #assignment-title       → title (string)
 *      - #assignment-due-date    → due_date (string, "YYYY-MM-DD")
 *      - #assignment-description → description (string)
 *      - #assignment-files       → split by newlines (\n) and filter
 *                                  empty strings to produce a files array.
 * 3. Check if the submit button (#add-assignment) has a data-edit-id
 *    attribute.
 *    - If it does, call handleUpdateAssignment() with that id and the
 *      field values.
 *    - If it does not, send a POST to './api/index.php' with the body:
 *        { title, due_date, description, files }
 *      On success (result.success === true):
 *        - Add the new assignment (with the id from result.id) to the
 *          global `assignments` array.
 *        - Call renderTable().
 *        - Reset the form.
 */
async function handleAddAssignment(event) {
  // ... your implementation here ...
    event.preventDefault();

  const title = document.getElementById('assignment-title').value;
  const due_date = document.getElementById('assignment-due-date').value;
  const description = document.getElementById('assignment-description').value;
  const files = document.getElementById('assignment-files').value
    .split('\n')
    .filter(f => f.trim() !== "");

  const btn = document.getElementById('add-assignment');
  const editId = btn.dataset.editId;

  if (editId) {
    await handleUpdateAssignment(editId, { title, due_date, description, files });
    return;
  }

  const res = await fetch('./api/index.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ title, due_date, description, files })
  });

  const result = await res.json();

  if (result.success) {
    assignments.push({
      id: result.id,
      title,
      due_date,
      description,
      files
    });

    renderTable();
    form.reset();
  }
}

/**
 * TODO: Implement handleUpdateAssignment (async).
 *
 * Parameters:
 *   id     — the integer primary key of the assignment being edited.
 *   fields — object with { title, due_date, description, files }.
 *
 * It should:
 * 1. Send a PUT to './api/index.php' with the body:
 *      { id, title, due_date, description, files }
 * 2. On success:
 *    - Update the matching entry in the global `assignments` array.
 *    - Call renderTable().
 *    - Reset the form.
 *    - Restore the submit button text to "Add Assignment" and remove
 *      its data-edit-id attribute.
 */
async function handleUpdateAssignment(id, fields) {
  // ... your implementation here ...
   const res = await fetch('./api/index.php', {
    method: 'PUT',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ id, ...fields })
  });

  const result = await res.json();

  if (result.success) {
    const index = assignments.findIndex(a => a.id == id);
    assignments[index] = { id: Number(id), ...fields };

    renderTable();

    form.reset();

    const btn = document.getElementById('add-assignment');
    btn.textContent = "Add Assignment";
    delete btn.dataset.editId;
  }
}

/**
 * TODO: Implement handleTableClick (async).
 *
 * This is a delegated click listener on the assignments table body.
 * It should:
 * 1. If event.target has class "delete-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Send a DELETE to './api/index.php?id=<id>'.
 *    c. On success, remove the assignment from the global `assignments`
 *       array and call renderTable().
 *
 * 2. If event.target has class "edit-btn":
 *    a. Read the integer id from event.target.dataset.id.
 *    b. Find the matching assignment in the global `assignments` array.
 *    c. Populate the form fields:
 *         #assignment-title       ← assignment.title
 *         #assignment-due-date    ← assignment.due_date
 *         #assignment-description ← assignment.description
 *         #assignment-files       ← assignment.files joined with newlines (\n)
 *    d. Change the submit button (#add-assignment) text to
 *       "Update Assignment" and set its data-edit-id attribute to the
 *       assignment's id.
 */
async function handleTableClick(event) {
  // ... your implementation here ...
  const button = event.target.closest('button');
  if (!button) return;

  const id = button.dataset.id;

  if (button.classList.contains('delete-btn')) {
    const res = await fetch(`./api/index.php?id=${id}`, {
      method: 'DELETE'
    });

    const result = await res.json();

    if (result.success) {
      assignments = assignments.filter(a => a.id != id);
      renderTable();
    }
  }

if (button.classList.contains('edit-btn')) {
    const assignment = assignments.find(a => a.id == id);

    document.getElementById('assignment-title').value = assignment.title;
    document.getElementById('assignment-due-date').value = assignment.due_date;
    document.getElementById('assignment-description').value = assignment.description;
    document.getElementById('assignment-files').value = Array.isArray(assignment.files) ? assignment.files.join('\n') : "";
    const btn = document.getElementById('add-assignment');
    btn.textContent = "Update Assignment";
    btn.dataset.editId = id;
  }
}

/**
 * TODO: Implement loadAndInitialize (async).
 *
 * It should:
 * 1. Send a GET to './api/index.php'.
 *    Response shape: { success: true, data: [ ...assignment objects ] }
 * 2. Store the data array in the global `assignments` variable.
 * 3. Call renderTable() to populate the table.
 * 4. Attach the 'submit' event listener to the assignment form
 *    (calls handleAddAssignment).
 * 5. Attach a 'click' event listener to the assignments table body
 *    (calls handleTableClick — event delegation for edit and delete).
 */
async function loadAndInitialize() {
  // ... your implementation here ...
  const res = await fetch('./api/index.php');
  const result = await res.json();

  if (result.success) {
    assignments = result.data;
    renderTable();
  }

  form.addEventListener('submit', handleAddAssignment);
  tbody.addEventListener('click', handleTableClick);

  if (toggleFormBtn && formSection) {
    toggleFormBtn.addEventListener('click', () => {
      formSection.classList.toggle('hidden');

      if (formSection.classList.contains('hidden')) {
        toggleFormBtn.textContent = "➕ Add New Assignment";
      } else {
        toggleFormBtn.textContent = "Close Form";
      }
    });
  }
}

// --- Initial Page Load ---
loadAndInitialize();
