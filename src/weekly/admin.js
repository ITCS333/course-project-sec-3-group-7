/*
  Requirement: Make the "Manage Weekly Breakdown" page interactive.
*/
 
// --- Global Data Store ---
let weeks = [];
 
// --- Element Selections ---
const weekForm   = document.getElementById("week-form");
const weeksTbody = document.getElementById("weeks-tbody");
 
// --- Functions ---
 
function createWeekRow(week) {
  const tr = document.createElement("tr");
 
  const tdTitle = document.createElement("td");
  tdTitle.textContent = week.title;
 
  const tdDate = document.createElement("td");
  tdDate.textContent = week.start_date;
 
  const tdDesc = document.createElement("td");
  tdDesc.textContent = week.description;
 
  const tdActions = document.createElement("td");
 
  const editBtn = document.createElement("button");
  editBtn.className  = "edit-btn";
  editBtn.dataset.id = week.id;
  editBtn.textContent = "Edit";
 
  const deleteBtn = document.createElement("button");
  deleteBtn.className  = "delete-btn";
  deleteBtn.dataset.id = week.id;
  deleteBtn.textContent = "Delete";
 
  tdActions.appendChild(editBtn);
  tdActions.appendChild(deleteBtn);
 
  tr.appendChild(tdTitle);
  tr.appendChild(tdDate);
  tr.appendChild(tdDesc);
  tr.appendChild(tdActions);
 
  return tr;
}
 
function renderTable() {
  weeksTbody.innerHTML = "";
  weeks.forEach((week) => {
    const row = createWeekRow(week);
    weeksTbody.appendChild(row);
  });
}
 
async function handleAddWeek(event) {
  event.preventDefault();
 
  const title       = document.getElementById("week-title").value;
  const start_date  = document.getElementById("week-start-date").value;
  const description = document.getElementById("week-description").value;
  const linksRaw    = document.getElementById("week-links").value;
  const links       = linksRaw.split("\n").map(l => l.trim()).filter(l => l !== "");
 
  const addBtn    = document.getElementById("add-week");
  const editId    = addBtn.dataset.editId;
 
  if (editId) {
    await handleUpdateWeek(Number(editId), { title, start_date, description, links });
  } else {
    try {
      const response = await fetch("./api/index.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ title, start_date, description, links }),
      });
      const result = await response.json();
 
      if (result.success === true) {
        weeks.push({ id: result.id, title, start_date, description, links });
        renderTable();
        weekForm.reset();
      }
    } catch (error) {
      console.error("Error adding week:", error);
    }
  }
}
 
async function handleUpdateWeek(id, fields) {
  try {
    const response = await fetch("./api/index.php", {
      method: "PUT",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, ...fields }),
    });
    const result = await response.json();
 
    if (result.success === true) {
      const index = weeks.findIndex((w) => w.id === id);
      if (index !== -1) {
        weeks[index] = { id, ...fields };
      }
      renderTable();
      weekForm.reset();
 
      const addBtn = document.getElementById("add-week");
      addBtn.textContent = "Add Week";
      delete addBtn.dataset.editId;
    }
  } catch (error) {
    console.error("Error updating week:", error);
  }
}
 
async function handleTableClick(event) {
  if (event.target.classList.contains("delete-btn")) {
    const id = Number(event.target.dataset.id);
    try {
      const response = await fetch(`./api/index.php?id=${id}`, {
        method: "DELETE",
      });
      const result = await response.json();
 
      if (result.success === true) {
        weeks = weeks.filter((w) => w.id !== id);
        renderTable();
      }
    } catch (error) {
      console.error("Error deleting week:", error);
    }
  }
 
  if (event.target.classList.contains("edit-btn")) {
    const id   = Number(event.target.dataset.id);
    const week = weeks.find((w) => w.id === id);
    if (!week) return;
 
    document.getElementById("week-title").value       = week.title;
    document.getElementById("week-start-date").value  = week.start_date;
    document.getElementById("week-description").value = week.description;
    document.getElementById("week-links").value       = (week.links || []).join("\n");
 
    const addBtn = document.getElementById("add-week");
    addBtn.textContent      = "Update Week";
    addBtn.dataset.editId   = week.id;
  }
}
 
async function loadAndInitialize() {
  try {
    const response = await fetch("./api/index.php");
    const result   = await response.json();
 
    if (result.success && Array.isArray(result.data)) {
      weeks = result.data;
    }
 
    renderTable();
  } catch (error) {
    console.error("Error loading weeks:", error);
  }
 
  weekForm.addEventListener("submit", handleAddWeek);
  weeksTbody.addEventListener("click", handleTableClick);
}
 
// --- Initial Page Load ---
loadAndInitialize();
 
loadAndInitialize();
