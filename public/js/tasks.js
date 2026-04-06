function showToast(message, type = 'success') {
    const toastContainer = document.getElementById('toastContainer');
    const toastId = 'toast-' + Date.now();
    const iconMap = {
        success: 'check-circle-fill',
        error: 'exclamation-triangle-fill',
        info: 'info-circle-fill',
    };
    const bgMap = {
        success: 'bg-success',
        error: 'bg-danger',
        info: 'bg-primary',
    };
    const toastHTML = `<div class="toast align-items-center text-white ${bgMap[type]} border-0" role="alert" aria-live="assertive" aria-atomic="true" id="${toastId}"><div class="d-flex"><div class="toast-body"><i class="bi bi-${iconMap[type]} me-2"></i>${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
    toastContainer.insertAdjacentHTML('beforeend', toastHTML);
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 4000,
    });
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}
function showLoading() {
    document.getElementById('loadingSpinner').classList.add('show');
}
function hideLoading() {
    document.getElementById('loadingSpinner').classList.remove('show');
}
const mobileMenuToggle = document.getElementById('mobileMenuToggle');
const leftPanel = document.getElementById('leftPanel');
const sidebarOverlay = document.getElementById('sidebarOverlay');
function toggleSidebar() {
    const isOpen = leftPanel.classList.contains('show');
    if (isOpen) {
        leftPanel.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        mobileMenuToggle.setAttribute('aria-expanded', 'false');
    } else {
        leftPanel.classList.add('show');
        sidebarOverlay.classList.add('show');
        mobileMenuToggle.setAttribute('aria-expanded', 'true');
    }
}
mobileMenuToggle.addEventListener('click', toggleSidebar);
sidebarOverlay.addEventListener('click', toggleSidebar);
document.querySelectorAll('.list-group-item-action').forEach((link) => {
    link.addEventListener('click', () => {
        if (window.innerWidth < 992) {
            setTimeout(toggleSidebar, 100);
        }
    });
});
const taskModal = new bootstrap.Modal(document.getElementById('taskModal'));
const taskForm = document.getElementById('taskForm');
const taskModalLabel = document.getElementById('taskModalLabel');
const saveTaskBtnText = document.getElementById('saveTaskBtnText');
const taskIdInput = document.getElementById('taskId');
const taskTitleInput = document.getElementById('taskTitle');
const taskDetailsInput = document.getElementById('taskDetails');
const taskDueDateInput = document.getElementById('taskDueDate');
const taskGroupIdInput = document.getElementById('taskGroupId');
document.getElementById('addTaskBtn')?.addEventListener('click', () => {
    openTaskModal();
});
document.getElementById('emptyStateAddBtn')?.addEventListener('click', () => {
    openTaskModal();
});
function openTaskModal(taskId = null) {
    taskForm.reset();
    taskForm.classList.remove('was-validated');
    if (taskId) {
        taskModalLabel.textContent = 'Edit Task';
        saveTaskBtnText.textContent = 'Update Task';
        showLoading();
        fetch(`?ajax=get_task&task_id=${taskId}`)
            .then((response) => response.json())
            .then((data) => {
                hideLoading();
                if (data.success) {
                    taskIdInput.value = data.task.id;
                    taskTitleInput.value = data.task.title;
                    taskDetailsInput.value = data.task.details || '';
                    taskDueDateInput.value = data.task.due_date || '';
                    taskGroupIdInput.value = data.task.group_id;
                    taskModal.show();
                } else {
                    showToast(data.message || 'Error loading task', 'error');
                }
            })
            .catch((error) => {
                hideLoading();
                showToast('Error loading task data', 'error');
                console.error('Error:', error);
            });
    } else {
        taskModalLabel.textContent = 'Add New Task';
        saveTaskBtnText.textContent = 'Add Task';
        taskIdInput.value = '';
        taskModal.show();
    }
}
document.addEventListener('click', (e) => {
    if (e.target.closest('.edit-task-btn')) {
        const btn = e.target.closest('.edit-task-btn');
        const taskId = btn.dataset.taskId;
        openTaskModal(taskId);
    }
});
taskForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!taskForm.checkValidity()) {
        e.stopPropagation();
        taskForm.classList.add('was-validated');
        return;
    }
    const formData = new FormData(taskForm);
    const taskId = taskIdInput.value;
    const isEdit = taskId !== '';
    const ajaxAction = isEdit ? 'edit_task' : 'add_task';
    showLoading();
    fetch(`?ajax=${ajaxAction}`, { method: 'POST', body: formData })
        .then((response) => response.json())
        .then((data) => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                taskModal.hide();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                showToast(data.message || 'Error saving task', 'error');
            }
        })
        .catch((error) => {
            hideLoading();
            showToast('Error saving task', 'error');
            console.error('Error:', error);
        });
});
const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
const deleteTaskTitle = document.getElementById('deleteTaskTitle');
const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
let deleteTaskId = null;
document.addEventListener('click', (e) => {
    if (e.target.closest('.delete-task-btn')) {
        e.preventDefault();
        const btn = e.target.closest('.delete-task-btn');
        deleteTaskId = btn.dataset.taskId;
        const taskTitle = btn.dataset.taskTitle;
        deleteTaskTitle.textContent = taskTitle;
        deleteModal.show();
    }
});
confirmDeleteBtn.addEventListener('click', () => {
    if (!deleteTaskId) return;
    const formData = new FormData();
    formData.append('task_id', deleteTaskId);
    showLoading();
    fetch('?ajax=delete_task', { method: 'POST', body: formData })
        .then((response) => response.json())
        .then((data) => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                deleteModal.hide();
                if (data.group_empty) {
                    setTimeout(() => {
                        window.location.href = '?';
                    }, 500);
                } else {
                    const taskRow = document.querySelector(
                        `tr[data-task-id="${deleteTaskId}"]`,
                    );
                    const taskCard = document.querySelector(
                        `.task-card[data-task-id="${deleteTaskId}"]`,
                    );
                    if (taskRow) taskRow.remove();
                    if (taskCard) taskCard.remove();
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                }
            } else {
                showToast(data.message || 'Error deleting task', 'error');
            }
        })
        .catch((error) => {
            hideLoading();
            showToast('Error deleting task', 'error');
            console.error('Error:', error);
        });
});
document.addEventListener('change', (e) => {
    if (e.target.classList.contains('move-task-select')) {
        const select = e.target;
        const taskId = select.dataset.taskId;
        const newGroupId = select.value;
        if (!newGroupId) return;
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('new_group_id', newGroupId);
        showLoading();
        fetch('?ajax=move_task', { method: 'POST', body: formData })
            .then((response) => response.json())
            .then((data) => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showToast(data.message || 'Error moving task', 'error');
                    select.value = '';
                }
            })
            .catch((error) => {
                hideLoading();
                showToast('Error moving task', 'error');
                console.error('Error:', error);
                select.value = '';
            });
    }
});
document.addEventListener('click', (e) => {
    if (e.target.closest('.move-task-link')) {
        e.preventDefault();
        const link = e.target.closest('.move-task-link');
        const taskId = link.dataset.taskId;
        const newGroupId = link.dataset.groupId;
        const formData = new FormData();
        formData.append('task_id', taskId);
        formData.append('new_group_id', newGroupId);
        showLoading();
        fetch('?ajax=move_task', { method: 'POST', body: formData })
            .then((response) => response.json())
            .then((data) => {
                hideLoading();
                if (data.success) {
                    showToast(data.message, 'success');
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    showToast(data.message || 'Error moving task', 'error');
                }
            })
            .catch((error) => {
                hideLoading();
                showToast('Error moving task', 'error');
                console.error('Error:', error);
            });
    }
});
const renameGroupModal = new bootstrap.Modal(
    document.getElementById('renameGroupModal'),
);
const renameGroupForm = document.getElementById('renameGroupForm');
const renameGroupIdInput = document.getElementById('renameGroupId');
const renameGroupNameInput = document.getElementById('renameGroupName');
document.addEventListener('click', (e) => {
    if (e.target.closest('.group-rename-btn')) {
        e.preventDefault();
        e.stopPropagation();
        const btn = e.target.closest('.group-rename-btn');
        const groupId = btn.dataset.groupId;
        const groupName = btn.dataset.groupName;
        renameGroupIdInput.value = groupId;
        renameGroupNameInput.value = groupName;
        renameGroupForm.classList.remove('was-validated');
        renameGroupModal.show();
    }
});
renameGroupForm.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!renameGroupForm.checkValidity()) {
        e.stopPropagation();
        renameGroupForm.classList.add('was-validated');
        return;
    }
    const formData = new FormData(renameGroupForm);
    showLoading();
    fetch('?ajax=rename_group', { method: 'POST', body: formData })
        .then((response) => response.json())
        .then((data) => {
            hideLoading();
            if (data.success) {
                showToast(data.message, 'success');
                renameGroupModal.hide();
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            } else {
                showToast(data.message || 'Error renaming group', 'error');
            }
        })
        .catch((error) => {
            hideLoading();
            showToast('Error renaming group', 'error');
            console.error('Error:', error);
        });
});
const shortcutsToggle = document.getElementById('shortcutsToggle');
const shortcutsList = document.getElementById('shortcutsList');
shortcutsToggle.addEventListener('click', () => {
    shortcutsList.classList.toggle('show');
});
document.addEventListener('click', (e) => {
    if (!e.target.closest('.keyboard-shortcuts')) {
        shortcutsList.classList.remove('show');
    }
});
document.addEventListener('keydown', (e) => {
    if (e.target.matches('input, textarea')) {
        if (e.key === 'Escape') {
            e.target.blur();
        }
        return;
    }
    switch (e.key.toLowerCase()) {
        case 'n':
            if (document.getElementById('addTaskBtn')) {
                e.preventDefault();
                openTaskModal();
            }
            break;
        case '/':
            e.preventDefault();
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
            break;
        case 's':
            if (window.innerWidth < 992) {
                e.preventDefault();
                toggleSidebar();
            }
            break;
        case '?':
            e.preventDefault();
            shortcutsList.classList.toggle('show');
            break;
        case 'escape':
            shortcutsList.classList.remove('show');
            break;
    }
});
document.getElementById('taskModal').addEventListener('shown.bs.modal', () => {
    taskTitleInput.focus();
});
document
    .getElementById('renameGroupModal')
    .addEventListener('shown.bs.modal', () => {
        renameGroupNameInput.focus();
        renameGroupNameInput.select();
    });
if (window.INITIAL_MESSAGE) {
    showToast(window.INITIAL_MESSAGE, 'success');
}
document.querySelectorAll('.alert-dismissible').forEach((alert) => {
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(alert);
        bsAlert.close();
    }, 5000);
});
document
    .getElementById('addGroupForm')
    .addEventListener('submit', function (e) {
        const input = document.getElementById('groupNameInput');
        if (!input.value.trim()) {
            e.preventDefault();
            input.classList.add('is-invalid');
            showToast('Please enter a group name', 'error');
        }
    });
document.querySelectorAll('.needs-validation').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }
        form.classList.add('was-validated');
    });
});
let formModified = false;
taskForm.addEventListener('input', () => {
    formModified = true;
});
taskForm.addEventListener('submit', () => {
    formModified = false;
});
document.getElementById('taskModal').addEventListener('hidden.bs.modal', () => {
    formModified = false;
});
console.log(
    '%cTask Manager',
    'font-size: 20px; font-weight: bold; color: #667eea;',
);
console.log('%cKeyboard Shortcuts:', 'font-weight: bold;');
console.log('  N - Add new task');
console.log('  / - Focus search');
console.log('  S - Toggle sidebar (mobile)');
console.log('  ? - Show shortcuts help');
console.log('  ESC - Close modals/dialogs');
