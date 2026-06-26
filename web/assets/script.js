let currentConfirmCallback = null;

function showAppModal(message, type = 'info', callback = null) {
    const modalEl = document.getElementById('appModal');
    let modal = bootstrap.Modal.getInstance(modalEl);
    if (!modal) modal = new bootstrap.Modal(modalEl);
    
    const iconWrap = document.getElementById('appModalIcon');
    const msgWrap = document.getElementById('appModalMessage');
    const actionWrap = document.getElementById('appModalActions');
    
    msgWrap.innerText = message;
    actionWrap.innerHTML = '';
    currentConfirmCallback = callback;

    if (type === 'success') {
        iconWrap.innerHTML = '<i class="bi bi-check-circle-fill text-success" style="font-size: 3.5rem;"></i>';
        actionWrap.innerHTML = `<button type="button" class="btn btn-success px-4" data-bs-dismiss="modal" onclick="executeConfirm()">OK</button>`;
    } else if (type === 'confirm') {
        iconWrap.innerHTML = '<i class="bi bi-question-circle-fill text-primary" style="font-size: 3.5rem;"></i>';
        actionWrap.innerHTML = `
            <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Hủy</button>
            <button type="button" class="btn btn-primary px-4" onclick="executeConfirm()">Đồng ý</button>
        `;
    } else {
        iconWrap.innerHTML = '<i class="bi bi-exclamation-circle-fill text-warning" style="font-size: 3.5rem;"></i>';
        actionWrap.innerHTML = `<button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" onclick="executeConfirm()">Đã hiểu</button>`;
    }
    
    modal.show();
}

function executeConfirm() {
    const modalEl = document.getElementById('appModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    if(modal) modal.hide();
    if (currentConfirmCallback) currentConfirmCallback();
}

function toggleMode(productId) {
    const mode = document.querySelector(`input[name="mode_${productId}"]:checked`).value;
    document.getElementById(`random_input_${productId}`).classList.toggle('d-none', mode !== 'random');
    document.getElementById(`select_input_${productId}`).classList.toggle('d-none', mode !== 'select');
}

function toggleProductSelect(productId) {
    const isChecked = document.getElementById(`check_${productId}`).checked;
    const configRow = document.getElementById(`config_${productId}`);
    const randomInput = document.querySelector(`input[name="random_count_${productId}"]`);
    
    if (isChecked) {
        configRow.classList.remove('d-none');
        const defaultCount = document.getElementById('default_random_count').value;
        if (defaultCount) {
            const maxStock = parseInt(randomInput.getAttribute('max')) || 0;
            const fillValue = parseInt(defaultCount);
            randomInput.value = fillValue > maxStock ? maxStock : fillValue;
        }
    } else {
        configRow.classList.add('d-none');
        if(randomInput) randomInput.value = ''; 
    }
}

function toggleSelectAll() {
    const isSelectAll = document.getElementById('selectAll').checked;
    const checkboxes = document.querySelectorAll('input[name="selected_products[]"]:not([disabled])');
    checkboxes.forEach(cb => {
        if (cb.checked !== isSelectAll) {
            cb.checked = isSelectAll;
            toggleProductSelect(cb.value);
        }
    });
}

let currentMemories = [];

async function fetchAndRenderMemories() {
    const formData = new FormData();
    formData.append('ajax_memory', 'get');
    
    const res = await fetch('core/ajax_memory.php', { method: 'POST', body: formData });
    currentMemories = await res.json();
    
    const listBody = document.getElementById('memory_list');
    const pinnedContainer = document.getElementById('pinned_memories_container');
    
    listBody.innerHTML = '';
    pinnedContainer.innerHTML = '';
    let hasPinned = false;

    if (currentMemories.length === 0) {
        listBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted small py-3">Chưa có cấu hình nào được lưu.</td></tr>';
        pinnedContainer.innerHTML = `<span class="text-muted small ms-2 fst-italic">Chưa có cấu hình nào được ghim.</span>`;
        return;
    }

    currentMemories.forEach(m => {
        const isPinned = parseInt(m.is_pinned) === 1;
        const isPinnedIcon = isPinned ? 'bi-pin-fill text-warning' : 'bi-pin-angle text-secondary';
        
        listBody.innerHTML += `
            <tr>
                <td class="align-middle">
                    <div class="fw-bold text-dark">${m.note}</div>
                    <div class="small text-muted font-monospace">${m.created_at}</div>
                </td>
                <td class="text-end align-middle text-nowrap" style="width: 1%;">
                    <button type="button" class="btn btn-sm btn-light border shadow-sm" onclick="togglePinMemory(${m.id})" title="Ghim/Bỏ ghim">
                        <i class="bi ${isPinnedIcon}"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-primary shadow-sm ms-1" onclick="loadMemory(${m.id})" title="Tải">
                        <i class="bi bi-download"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="deleteMemory(${m.id})" title="Xóa">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            </tr>
        `;

        if(isPinned) {
            hasPinned = true;
            pinnedContainer.innerHTML += `
                <button type="button" class="memory-chip" onclick="loadMemory(${m.id})">
                    <i class="bi bi-pin-fill me-1"></i> ${m.note}
                </button>
            `;
        }
    });

    if(!hasPinned) {
        pinnedContainer.innerHTML = `<span class="text-muted small ms-2 fst-italic">Chưa có cấu hình nào được ghim.</span>`;
    }
}

async function saveNewMemory() {
    const note = document.getElementById('memory_note').value.trim();
    const isPinned = document.getElementById('memory_is_pinned').checked;

    if (!note) {
        showAppModal('Vui lòng nhập ghi chú cho cấu hình này nhé!', 'info', () => {
            document.getElementById('memory_note').focus();
        });
        return;
    }

    const dataToSave = {
        defaultCount: document.getElementById('default_random_count').value,
        globalInterval: document.querySelector('input[name="global_interval"]').value,
        globalSortMode: document.querySelector('select[name="global_sort_mode"]').value,
        products: {}
    };

    document.querySelectorAll('input[name="selected_products[]"]:checked').forEach(cb => {
        const pid = cb.value;
        const mode = document.querySelector(`input[name="mode_${pid}"]:checked`).value;
        const randomCount = document.querySelector(`input[name="random_count_${pid}"]`).value;
        
        const selectedFiles = [];
        if (mode === 'select') {
            document.querySelectorAll(`input[name="files_${pid}[]"]:checked`).forEach(fileCb => {
                selectedFiles.push(fileCb.value);
            });
        }
        dataToSave.products[pid] = { mode, randomCount, selectedFiles };
    });

    const formData = new FormData();
    formData.append('ajax_memory', 'save');
    formData.append('note', note);
    formData.append('is_pinned', isPinned ? 1 : 0);
    formData.append('config_data', JSON.stringify(dataToSave));

    await fetch('core/ajax_memory.php', { method: 'POST', body: formData });
    
    document.getElementById('memory_note').value = '';
    document.getElementById('memory_is_pinned').checked = false;
    
    fetchAndRenderMemories();
    showAppModal('Đã lưu cấu hình lên Database thành công! ☁️', 'success');
}

function deleteMemory(id) {
    showAppModal('Bạn có chắc chắn muốn xóa cấu hình này khỏi Database?', 'confirm', async () => {
        const formData = new FormData();
        formData.append('ajax_memory', 'delete');
        formData.append('id', id);
        
        await fetch('core/ajax_memory.php', { method: 'POST', body: formData });
        fetchAndRenderMemories();
    });
}

async function togglePinMemory(id) {
    const formData = new FormData();
    formData.append('ajax_memory', 'toggle_pin');
    formData.append('id', id);
    
    await fetch('core/ajax_memory.php', { method: 'POST', body: formData });
    fetchAndRenderMemories();
}

function loadMemory(id) {
    showAppModal('Tải cấu hình này sẽ ghi đè các thiết lập hiện tại đang có trên màn hình. Bạn chắc chắn chứ?', 'confirm', () => {
        const memory = currentMemories.find(m => m.id == id);
        if (!memory) return;

        const data = JSON.parse(memory.config_data);

        if (data.defaultCount) document.getElementById('default_random_count').value = data.defaultCount;
        if (data.globalInterval) document.querySelector('input[name="global_interval"]').value = data.globalInterval;
        if (data.globalSortMode) document.querySelector('select[name="global_sort_mode"]').value = data.globalSortMode;

        document.getElementById('selectAll').checked = false;
        document.querySelectorAll('input[name="selected_products[]"]').forEach(cb => {
            cb.checked = false;
            toggleProductSelect(cb.value);
            document.querySelectorAll(`input[name="files_${cb.value}[]"]`).forEach(f => f.checked = false);
        });

        for (const pid in data.products) {
            const cb = document.getElementById(`check_${pid}`);
            if (cb) {
                cb.checked = true;
                const prodData = data.products[pid];

                const modeInput = document.getElementById(`mode_${prodData.mode}_${pid}`);
                if (modeInput) {
                    modeInput.checked = true;
                    toggleMode(pid);
                }

                document.getElementById(`config_${pid}`).classList.remove('d-none');

                const randomInput = document.querySelector(`input[name="random_count_${pid}"]`);
                if (randomInput && prodData.randomCount) {
                    randomInput.value = prodData.randomCount;
                }

                if (prodData.mode === 'select' && prodData.selectedFiles) {
                    prodData.selectedFiles.forEach(file => {
                        const safeFile = file.replace(/"/g, '\\"');
                        const fileCb = document.querySelector(`input[name="files_${pid}[]"][value="${safeFile}"]`);
                        if (fileCb) fileCb.checked = true;
                    });
                }
            }
        }
        
        const dbModalEl = document.getElementById('memoryModal');
        if (dbModalEl) {
            const dbModalInstance = bootstrap.Modal.getInstance(dbModalEl);
            if (dbModalInstance) dbModalInstance.hide();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    fetchAndRenderMemories();
    const memoryModal = document.getElementById('memoryModal');
    if(memoryModal) {
        memoryModal.addEventListener('show.bs.modal', fetchAndRenderMemories);
    }
});