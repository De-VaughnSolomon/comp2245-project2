document.addEventListener("DOMContentLoaded", function () {
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.content : "";

    // TICKET LIST
    const ticketList = document.getElementById("ticket-list");
    const ticketCount = document.getElementById("ticketcount");
    const filterButtons = document.querySelectorAll("[data-filter]");

    function makeCell(value) {
        const cell = document.createElement("td");
        cell.textContent = value == null ? "" : String(value);
        return cell;
    }

    async function loadTickets(filter) {
        if (!ticketList || !ticketCount) return;
        ticketList.replaceChildren();
        const loadingRow = document.createElement("tr");
        const loadingCell = makeCell("Loading tickets...");
        loadingCell.colSpan = 7;
        loadingRow.appendChild(loadingCell);
        ticketList.appendChild(loadingRow);
        ticketCount.textContent = "Loading tickets...";
        try {
            const response = await fetch("api/tickets.php?filter=" + encodeURIComponent(filter));
            const result = await response.json();
            if (!response.ok) throw new Error(result.error || "Could not load tickets.");
            ticketList.replaceChildren();
            ticketCount.textContent = result.count + (result.count === 1 ? " ticket" : " tickets");
            if (result.tickets.length === 0) {
                const row = document.createElement("tr");
                const cell = makeCell("No tickets found.");
                cell.colSpan = 7;
                row.appendChild(cell);
                ticketList.appendChild(row);
                return;
            }
            result.tickets.forEach(function (ticket) {
                const row = document.createElement("tr");
                row.appendChild(makeCell(ticket.id));
                const titleCell = document.createElement("td");
                const link = document.createElement("a");
                link.href = "ticket.php?id=" + encodeURIComponent(ticket.id);
                link.textContent = ticket.title;
                titleCell.appendChild(link);
                row.appendChild(titleCell);
                row.appendChild(makeCell(ticket.priority));
                row.appendChild(makeCell(ticket.status));
                row.appendChild(makeCell(ticket.created_by_name));
                row.appendChild(makeCell(ticket.assigned_to_name || "Nobody"));
                row.appendChild(makeCell(ticket.created_at));
                ticketList.appendChild(row);
            });
        } catch (error) {
            ticketCount.textContent = "Could not load tickets.";
            ticketList.replaceChildren();
            const row = document.createElement("tr");
            const cell = makeCell("Could not load tickets. Please try again.");
            cell.colSpan = 7;
            row.appendChild(cell);
            ticketList.appendChild(row);
        }
    }

    filterButtons.forEach(function (button) {
        button.addEventListener("click", function () {
            filterButtons.forEach(function (item) { item.classList.remove("active"); });
            button.classList.add("active");
            loadTickets(button.dataset.filter);
        });
    });
    if (ticketList) loadTickets("all");

    // TICKET DETAILS
    if (typeof ticketId === "undefined") return;

    async function sendUpdate(action, extra) {
        const response = await fetch("api/ticket-update.php", {
            method: "POST",
            headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken },
            body: JSON.stringify(Object.assign({ action: action, ticket_id: ticketId }, extra || {}))
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || "Request failed.");
        return result;
    }

    function updateTime(time) {
        const target = document.getElementById("updated-at");
        if (target) target.textContent = time;
    }

    const status = document.getElementById("status");
    if (status) {
        status.dataset.oldStatus = status.value;
        status.addEventListener("change", async function () {
            const oldStatus = status.dataset.oldStatus;
            status.disabled = true;
            const message = document.getElementById("status-message");
            message.textContent = "Saving...";
            try {
                const result = await sendUpdate("status", { status: status.value });
                status.dataset.oldStatus = result.ticket.status;
                updateTime(result.ticket.updated_at);
                message.textContent = "Status updated.";
            } catch (error) {
                status.value = oldStatus;
                message.textContent = error.message;
            } finally { status.disabled = false; }
        });
    }

    const assignButton = document.getElementById("assign-button");
    if (assignButton) {
        assignButton.addEventListener("click", async function () {
            assignButton.disabled = true;
            try {
                const result = await sendUpdate("assign");
                document.getElementById("assigned-to").textContent = result.assigned_to || "Nobody";
                updateTime(result.updated_at);
                assignButton.remove();
            } catch (error) { alert(error.message); assignButton.disabled = false; }
        });
    }

    // Notes are created only by api/notes.php.
    const noteForm = document.getElementById("note-form");
    if (noteForm) {
        let sendingNote = false;
        noteForm.addEventListener("submit", async function (event) {
            event.preventDefault();
            if (sendingNote) return;
            const noteBody = document.getElementById("note-body");
            const noteMessage = document.getElementById("note-message");
            const body = noteBody.value.trim();
            if (!body) { noteMessage.textContent = "Please enter a note."; return; }
            const button = noteForm.querySelector("button[type='submit']");
            sendingNote = true;
            button.disabled = true;
            noteMessage.textContent = "Adding note...";
            try {
                const response = await fetch("api/notes.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/json", "X-CSRF-TOKEN": csrfToken },
                    body: JSON.stringify({ ticket_id: ticketId, body: body })
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.error || "Could not add note.");
                const note = result.note;
                const notesList = document.getElementById("notes-list");
                const emptyMessage = document.getElementById("no-notes");
                if (emptyMessage) emptyMessage.remove();
                const noteDiv = document.createElement("div");
                noteDiv.className = "note";
                const authorParagraph = document.createElement("p");
                const author = document.createElement("strong");
                author.textContent = note.author_name;
                authorParagraph.appendChild(author);
                const bodyParagraph = document.createElement("p");
                bodyParagraph.textContent = note.body;
                const date = document.createElement("small");
                date.textContent = note.created_at;
                noteDiv.append(authorParagraph, bodyParagraph, date);
                notesList.appendChild(noteDiv);
                noteBody.value = "";
                noteMessage.textContent = "Note added.";
            } catch (error) { noteMessage.textContent = error.message; }
            finally { sendingNote = false; button.disabled = false; }
        });
    }

    const deleteButton = document.getElementById("delete-button");
    const confirmation = document.getElementById("delete-confirmation");
    const confirmDelete = document.getElementById("confirm-delete");
    const cancelDelete = document.getElementById("cancel-delete");
    if (deleteButton) deleteButton.addEventListener("click", function () { confirmation.hidden = false; deleteButton.hidden = true; });
    if (cancelDelete) cancelDelete.addEventListener("click", function () { confirmation.hidden = true; deleteButton.hidden = false; });
    if (confirmDelete) {
        confirmDelete.addEventListener("click", async function () {
            confirmDelete.disabled = true;
            try {
                await sendUpdate("delete");
                window.location.href = "index.php?dealeted=1";
            } catch (error) { alert(error.message); confirmDelete.disabled = false; }
        });
    }
});
