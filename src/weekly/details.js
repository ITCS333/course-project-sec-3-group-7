/*
  Requirement: Populate the weekly detail page and handle the discussion forum.
*/

// --- Global Data Store ---
let currentWeekId   = null;
let currentComments = [];

// --- Functions ---

function getWeekIdFromURL() {
  const params = new URLSearchParams(window.location.search);
  return params.get("id");
}

function renderWeekDetails(week) {
  document.getElementById("week-title").textContent       = week.title;
  document.getElementById("week-start-date").textContent  = "Starts on: " + week.start_date;
  document.getElementById("week-description").textContent = week.description;

  const weekLinksList = document.getElementById("week-links-list");
  weekLinksList.innerHTML = "";
  (week.links || []).forEach((url) => {
    const li = document.createElement("li");
    const a  = document.createElement("a");
    a.href        = url;
    a.textContent = url;
    li.appendChild(a);
    weekLinksList.appendChild(li);
  });
}

function createCommentArticle(comment) {
  const article = document.createElement("article");

  const p = document.createElement("p");
  p.textContent = comment.text;

  const footer = document.createElement("footer");
  footer.textContent = "Posted by: " + comment.author;

  article.appendChild(p);
  article.appendChild(footer);

  return article;
}

function renderComments() {
  const commentList = document.getElementById("comment-list");
  commentList.innerHTML = "";
  currentComments.forEach((comment) => {
    commentList.appendChild(createCommentArticle(comment));
  });
}

async function handleAddComment(event) {
  event.preventDefault();

  const newCommentInput = document.getElementById("new-comment");
  const commentText = newCommentInput.value.trim();
  if (!commentText) return;

  try {
    const response = await fetch("./api/index.php?action=comment", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        week_id: currentWeekId,
        author:  "Student",
        text:    commentText,
      }),
    });
    const result = await response.json();

    if (result.success === true) {
      currentComments.push(result.data);
      renderComments();
      newCommentInput.value = "";
    }
  } catch (error) {
    console.error("Error posting comment:", error);
  }
}

async function initializePage() {
  currentWeekId = getWeekIdFromURL();

  if (!currentWeekId) {
    document.getElementById("week-title").textContent = "Week not found.";
    return;
  }

  try {
    const [weekResponse, commentsResponse] = await Promise.all([
      fetch(`./api/index.php?id=${currentWeekId}`),
      fetch(`./api/index.php?action=comments&week_id=${currentWeekId}`),
    ]);

    const weekResult     = await weekResponse.json();
    const commentsResult = await commentsResponse.json();

    currentComments = (commentsResult.success && Array.isArray(commentsResult.data))
      ? commentsResult.data
      : [];

    if (weekResult.success && weekResult.data) {
      renderWeekDetails(weekResult.data);
      renderComments();
      document.getElementById("comment-form")
        .addEventListener("submit", handleAddComment);
    } else {
      document.getElementById("week-title").textContent = "Week not found.";
    }
  } catch (error) {
    console.error("Error initializing page:", error);
    document.getElementById("week-title").textContent = "Week not found.";
  }
}

// --- Initial Page Load ---
if (typeof module === "undefined") {
  initializePage();
}

// --- Exports (for autograder) ---
if (typeof module !== "undefined") {
  module.exports = {
    getWeekIdFromURL,
    renderWeekDetails,
    createCommentArticle,
    renderComments,
    handleAddComment,
    initializePage,
  };
}
initializePage();
