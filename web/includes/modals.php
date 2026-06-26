<div class="modal fade" id="memoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-cloud text-primary me-2"></i>Đồng bộ cấu hình Đám Mây</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4 bg-white p-3 border rounded-3 shadow-sm">
                    <label class="form-label fw-semibold small text-muted">Lưu trạng thái hiện tại (Lên Server):</label>
                    <div class="input-group mb-2">
                        <input type="text" id="memory_note" class="form-control shadow-none" placeholder="VD: Chiến dịch 15/06...">
                        <button type="button" class="btn btn-primary" onclick="saveNewMemory()">
                            <i class="bi bi-cloud-upload"></i> Lưu
                        </button>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input shadow-none" type="checkbox" id="memory_is_pinned">
                        <label class="form-check-label small text-muted" for="memory_is_pinned">Ghim ra màn hình chính</label>
                    </div>
                </div>

                <h6 class="fw-bold mb-3 text-dark">Các cấu hình trên Database:</h6>
                <div class="table-responsive border rounded-3" style="max-height: 250px; overflow-y: auto;">
                    <table class="table table-hover mb-0">
                        <tbody id="memory_list">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="appModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow">
            <div class="modal-body text-center p-4">
                <div id="appModalIcon" class="mb-3"></div>
                <h6 id="appModalMessage" class="fw-bold mb-4 text-dark" style="line-height: 1.5;"></h6>
                <div class="d-flex justify-content-center gap-2" id="appModalActions">
                    </div>
            </div>
        </div>
    </div>
</div>