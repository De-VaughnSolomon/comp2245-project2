
document.addEventListener("DOMContentLoaded", function () {

    // TICKET LIST
    const ticketList = document.getElementById("ticket-list");
    const ticketCount = document.getElementById("ticketcount");
    const filterButtons = document.querySelectorAll("[data-filter]");

    async function loadTickets(filter) {

        ticketList.innerHTML = `
            <tr>
                <td colspan="7">Loading tickets...</td>
            </tr>
        `;

        ticketCount.textContent = "Loading tickets...";

        try {
            const response = await fetch(
                "index.php?ajax=tickets&filter=" + encodeURIComponent(filter)
            );

            if (!response.ok) {
                throw new Error("Could not load tickets.");
            }

            const tickets = await response.json();

            ticketList.innerHTML = "";

            ticketCount.textContent =
                tickets.length + (tickets.length === 1 ? " ticket" : " tickets");

            if (tickets.length === 0) {
                ticketList.innerHTML = `
                    <tr>
                        <td colspan="7">No tickets found.</td>
                    </tr>
                `;
                return;
            }

            tickets.forEach(function (ticket) {

                const row = document.createElement("tr");

                const id = document.createElement("td");
                id.textContent = ticket.id;

                const title = document.createElement("td");
                const titleLink = document.createElement("a");

                titleLink.href = "ticket.php?id=" +
                    encodeURIComponent(ticket.id);

                titleLink.textContent = ticket.title;
                title.appendChild(titleLink);

                const priority = document.createElement("td");
                priority.textContent = ticket.priority;

                const status = document.createElement("td");
                status.textContent = ticket.status;

                const creator = document.createElement("td");
                creator.textContent =
                    ticket.creator_firstname + " " +
                    ticket.creator_lastname;

                const assignee = document.createElement("td");

                if (ticket.assignee_firstname) {
                    assignee.textContent =
                        ticket.assignee_firstname + " " +
                        ticket.assignee_lastname;
                } else {
                    assignee.textContent = "Nobody";
                }

                const created = document.createElement("td");
                created.textContent = ticket.created_at;

                row.appendChild(id);
                row.appendChild(title);
                row.appendChild(priority);
                row.appendChild(status);
                row.appendChild(creator);
                row.appendChild(assignee);
                row.appendChild(created);

                ticketList.appendChild(row);
            });

        } catch (error) {

            ticketCount.textContent = "Could not load tickets.";

            ticketList.innerHTML = `
                <tr>
                    <td colspan="7">
                        Could not load tickets. Please try again.
                    </td>
                </tr>
            `;
        }
    }

    // FILTER BUTTONS
    filterButtons.forEach(function (button) {

        button.addEventListener("click", function () {

            filterButtons.forEach(function (btn) {
                btn.classList.remove("active");
            });

            button.classList.add("active");

            loadTickets(button.dataset.filter);
        });

    });

    if (ticketList) {
        loadTickets("all");
    }

});


// TICKET DETAILS
document.addEventListener("DOMContentLoaded", function () {

    // Check if this is the ticket details page
    if (typeof ticketId === "undefined") {
        return;
    }

    // SEND AJAX REQUEST
    async function sendRequest(action, extra = {}) {

        const response = await fetch("ticket.php?id=" + ticketId, {

            method: "POST",

            headers: {
                "Content-Type": "application/json"
            },

            body: JSON.stringify({
                action: action,
                ticket_id: ticketId,
                csrf_token: csrfToken,
                ...extra
            })

        });

        const result = await response.json();

        if (!response.ok) {
            throw new Error(result.message || "Request failed.");
        }

        return result;
    }

    // UPDATE LAST UPDATED
    function updateTime(time) {

        document.getElementById("updated-at").textContent = time;

    }

    // CHANGE STATUS
    const status = document.getElementById("status");

    if (status) {

        status.addEventListener("change", async function () {

            const oldStatus = status.dataset.oldStatus || status.value;

            status.disabled = true;

            document.getElementById("status-message").textContent =
                "Saving...";

            try {

                const result = await sendRequest("status", {
                    status: status.value
                });

                updateTime(result.updated_at);

                status.dataset.oldStatus = result.status;

                document.getElementById("status-message").textContent =
                    "Status updated.";

            } catch (error) {

                status.value = oldStatus;

                document.getElementById("status-message").textContent =
                    error.message;

            } finally {

                status.disabled = false;

            }

        });

        status.dataset.oldStatus = status.value;
    }

    // ASSIGN TO ME
    const assignButton = document.getElementById("assign-button");

    if (assignButton) {

        assignButton.addEventListener("click", async function () {

            assignButton.disabled = true;

            try {

                const result = await sendRequest("assign");

                document.getElementById("assigned-to").textContent =
                    result.assigned_to;

                updateTime(result.updated_at);

                // Hide button after assignment
                assignButton.remove();

            } catch (error) {

                alert(error.message);

                assignButton.disabled = false;

            }

        });
    }

    // ADD NOTE
    const noteForm = document.getElementById("note-form");

    if (noteForm) {

        noteForm.addEventListener("submit", async function (event) {

            event.preventDefault();

            const noteBody = document.getElementById("note-body");
            const noteMessage = document.getElementById("note-message");

            const body = noteBody.value.trim();

            // Do not allow empty notes
            if (body === "") {

                noteMessage.textContent = "Please enter a note.";
                return;

            }

            const submitButton = noteForm.querySelector("button");

            submitButton.disabled = true;

            noteMessage.textContent = "Adding note...";

            try {

                const result = await sendRequest("note", {
                    body: body
                });

                const note = result.note;

                const notesList = document.getElementById("notes-list");

                // Remove No notes yet message
                const noNotes = document.getElementById("no-notes");

                if (noNotes) {
                    noNotes.remove();
                }

                // Create note
                const noteDiv = document.createElement("div");
                noteDiv.className = "note";

                const author = document.createElement("strong");

                author.textContent =
                    note.firstname + " " + note.lastname;

                const authorParagraph = document.createElement("p");
                authorParagraph.appendChild(author);

                const bodyParagraph = document.createElement("p");
                bodyParagraph.textContent = note.body;

                const date = document.createElement("small");
                date.textContent = note.created_at;

                noteDiv.appendChild(authorParagraph);
                noteDiv.appendChild(bodyParagraph);
                noteDiv.appendChild(date);

                // Add note to bottom
                notesList.appendChild(noteDiv);

                // Clear textarea
                noteBody.value = "";

                noteMessage.textContent = "Note added.";

            } catch (error) {

                noteMessage.textContent = error.message;

            } finally {

                submitButton.disabled = false;

            }

        });
    }

    // DELETE CONFIRMATION
    const deleteButton = document.getElementById("delete-button");

    const confirmation = document.getElementById("delete-confirmation");

    const confirmDelete = document.getElementById("confirm-delete");

    const cancelDelete = document.getElementById("cancel-delete");

    // Show confirmation
    if (deleteButton) {

        deleteButton.addEventListener("click", function () {

            confirmation.hidden = false;

            deleteButton.hidden = true;

        });

    }

    // Cancel deletion
    if (cancelDelete) {

        cancelDelete.addEventListener("click", function () {

            confirmation.hidden = true;

            deleteButton.hidden = false;

        });

    }

    // Confirm deletion
    if (confirmDelete) {

        confirmDelete.addEventListener("click", async function () {

            confirmDelete.disabled = true;

            try {

                await sendRequest("delete");

                // Return to ticket list
                window.location.href = "index.php?deleted=1";

            } catch (error) {

                alert(error.message);

                confirmDelete.disabled = false;

            }

        });

    }

});
