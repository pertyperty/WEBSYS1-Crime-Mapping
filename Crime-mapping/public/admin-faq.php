<?php
require __DIR__ . '/guard.php';
requireRole(['admin']);
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>FAQ Management | Admin</title>
    <link rel="icon" type="image/png" href="../assets/images/logo/la-trinidad.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/css/site.css" />
    <style>
        .faq-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 24px;
        }
        
        .faq-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .faq-actions {
            display: flex;
            gap: 12px;
        }
        
        .faq-list {
            display: grid;
            gap: 16px;
        }
        
        .faq-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 16px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }
        
        .faq-item-content {
            flex: 1;
            min-width: 0;
        }
        
        .faq-item-question {
            font-weight: 600;
            margin: 0 0 8px 0;
            color: var(--text);
        }
        
        .faq-item-answer {
            font-size: 13px;
            color: var(--text);
            line-height: 1.5;
            margin-bottom: 8px;
        }
        
        .faq-item-meta {
            font-size: 12px;
            color: var(--muted);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .faq-item-meta span {
            display: inline-block;
        }
        
        .faq-item-actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }
        
        .btn-icon {
            padding: 8px 12px;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--surface);
            color: var(--text);
            transition: all 0.2s;
        }
        
        .btn-icon:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-icon.danger:hover {
            background: #ef4444;
            border-color: #ef4444;
        }
        
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.is-open {
            display: flex;
        }
        
        .modal-content {
            background: var(--surface);
            border-radius: 12px;
            padding: 24px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border);
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 18px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: var(--text);
            padding: 4px 8px;
        }
        
        .form-grid {
            display: grid;
            gap: 16px;
        }
        
        .form-grid label {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        
        .form-grid label span {
            font-weight: 500;
            font-size: 13px;
        }
        
        .form-grid input,
        .form-grid textarea,
        .form-grid select {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            background: var(--background);
            color: var(--text);
            font-size: 13px;
            font-family: inherit;
        }
        
        .form-grid textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .modal-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
    </style>
</head>
<body class="page-admin-faq">
    <div class="page-shell">
        <header class="site-header">
            <div class="brand">
                <img class="brand-logo" src="../assets/images/logo/la-trinidad.png" alt="La Trinidad logo" />
                <div>
                    <div class="brand-title">FAQ Management</div>
                    <div class="brand-subtitle">Manage help articles</div>
                </div>
            </div>
            <?php require_once __DIR__ . '/_navbar.php'; render_navbar('faq', 'admin'); ?>
        </header>

        <main class="faq-container">
            <section>
                <div class="faq-header">
                    <div>
                        <p class="eyebrow">Content Management</p>
                        <h1>Frequently Asked Questions</h1>
                    </div>
                    <div class="faq-actions">
                        <button class="btn-primary" id="add-faq-btn">Add FAQ</button>
                    </div>
                </div>
                
                <div id="faq-status" class="muted u-mb-16"></div>
                <div class="faq-list" id="faq-list">
                    <p class="muted">Loading FAQs...</p>
                </div>
            </section>
        </main>
    </div>

    <!-- FAQ Form Modal -->
    <div class="modal-overlay" id="faq-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modal-title">Add FAQ</h2>
                <button class="modal-close" id="close-modal">×</button>
            </div>
            <form id="faq-form" class="form-grid">
                <label>
                    <span>Question *</span>
                    <input type="text" id="faq-question" placeholder="What is...?" required />
                </label>
                <label>
                    <span>Answer *</span>
                    <textarea id="faq-answer" placeholder="Provide a detailed answer..." required></textarea>
                </label>
                <label>
                    <span>Category</span>
                    <input type="text" id="faq-category" placeholder="e.g., General, Reporting, Verification" />
                </label>
                <label>
                    <span>Sort Order</span>
                    <input type="number" id="faq-sort-order" placeholder="0" value="0" />
                </label>
                <label>
                    <span>
                        <input type="checkbox" id="faq-is-active" checked />
                        Active
                    </span>
                </label>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" id="cancel-btn">Cancel</button>
                    <button type="submit" class="btn-primary">Save</button>
                </div>
                <p id="form-status" class="muted"></p>
            </form>
        </div>
    </div>

    <script>
        const csrfToken = <?php echo json_encode($csrfToken); ?>;
        let currentFaqId = null;
        let faqs = [];

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        async function loadFAQs() {
            try {
                const resp = await fetch('../api/faq.php?include_inactive=1');
                const result = await resp.json();
                if (!result.ok) {
                    document.getElementById('faq-list').innerHTML = '<p class="muted">Failed to load FAQs</p>';
                    return;
                }
                faqs = Array.isArray(result.data) ? result.data.map((faq) => ({
                    ...faq,
                    question: String(faq.question ?? ''),
                    answer: String(faq.answer ?? ''),
                    category: String(faq.category ?? '')
                })) : [];
                renderFAQs();
            } catch (e) {
                console.error('Failed to load FAQs', e);
                document.getElementById('faq-list').innerHTML = '<p class="muted">Error loading FAQs</p>';
            }
        }

        function renderFAQs() {
            const container = document.getElementById('faq-list');
            if (faqs.length === 0) {
                container.innerHTML = '<p class="muted">No FAQs yet. Add one to get started.</p>';
                return;
            }
            
            container.innerHTML = faqs.map(faq => `
                <div class="faq-item">
                    <div class="faq-item-content">
                        <h3 class="faq-item-question">${escapeHtml(faq.question)}</h3>
                        <p class="faq-item-answer">${escapeHtml(faq.answer.substring(0, 150))}${faq.answer.length > 150 ? '...' : ''}</p>
                        <div class="faq-item-meta">
                            ${faq.category ? `<span><strong>Category:</strong> ${escapeHtml(faq.category)}</span>` : ''}
                            ${faq.is_active !== undefined ? `<span>${faq.is_active ? '✓ Active' : '✗ Inactive'}</span>` : ''}
                        </div>
                    </div>
                    <div class="faq-item-actions">
                        <button class="btn-icon" onclick="editFAQ(${faq.faq_id})">Edit</button>
                        <button class="btn-icon danger" onclick="deleteFAQ(${faq.faq_id})">Delete</button>
                    </div>
                </div>
            `).join('');
        }

        function editFAQ(faqId) {
            const faq = faqs.find(f => f.faq_id === faqId);
            if (!faq) return;
            
            currentFaqId = faqId;
            document.getElementById('modal-title').textContent = 'Edit FAQ';
            document.getElementById('faq-question').value = faq.question;
            document.getElementById('faq-answer').value = faq.answer;
            document.getElementById('faq-category').value = faq.category || '';
            document.getElementById('faq-sort-order').value = faq.sort_order || 0;
            document.getElementById('faq-is-active').checked = faq.is_active !== false;
            
            document.getElementById('faq-modal').classList.add('is-open');
        }

        async function deleteFAQ(faqId) {
            if (!confirm('Are you sure you want to delete this FAQ?')) return;
            
            try {
                const resp = await fetch('../api/faq.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ action: 'delete', faq_id: faqId })
                });
                
                const result = await resp.json();
                if (result.ok) {
                    document.getElementById('faq-status').textContent = 'FAQ deleted.';
                    loadFAQs();
                } else {
                    alert('Failed to delete FAQ: ' + (result.error || 'Unknown error'));
                }
            } catch (e) {
                alert('Error deleting FAQ: ' + e.message);
            }
        }

        document.getElementById('add-faq-btn').addEventListener('click', () => {
            currentFaqId = null;
            document.getElementById('modal-title').textContent = 'Add FAQ';
            document.getElementById('faq-form').reset();
            document.getElementById('faq-is-active').checked = true;
            document.getElementById('faq-sort-order').value = 0;
            document.getElementById('faq-modal').classList.add('is-open');
        });

        document.getElementById('close-modal').addEventListener('click', () => {
            document.getElementById('faq-modal').classList.remove('is-open');
        });

        document.getElementById('cancel-btn').addEventListener('click', () => {
            document.getElementById('faq-modal').classList.remove('is-open');
        });

        document.getElementById('faq-form').addEventListener('submit', async (ev) => {
            ev.preventDefault();
            
            const formStatus = document.getElementById('form-status');
            formStatus.textContent = 'Saving...';
            
            try {
                const payload = {
                    action: currentFaqId ? 'update' : 'create',
                    question: document.getElementById('faq-question').value.trim(),
                    answer: document.getElementById('faq-answer').value.trim(),
                    category: document.getElementById('faq-category').value.trim(),
                    sort_order: parseInt(document.getElementById('faq-sort-order').value) || 0,
                    is_active: document.getElementById('faq-is-active').checked ? 1 : 0
                };
                
                if (currentFaqId) {
                    payload.faq_id = currentFaqId;
                }
                
                const resp = await fetch('../api/faq.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify(payload)
                });
                
                const result = await resp.json();
                if (result.ok) {
                    formStatus.textContent = 'Saved!';
                    setTimeout(() => {
                        document.getElementById('faq-modal').classList.remove('is-open');
                        loadFAQs();
                        document.getElementById('faq-status').textContent = `FAQ ${currentFaqId ? 'updated' : 'created'}.`;
                    }, 1000);
                } else {
                    formStatus.textContent = result.error || 'Save failed.';
                }
            } catch (e) {
                formStatus.textContent = 'Error: ' + e.message;
            }
        });

        
        loadFAQs();
    </script>
</body>
</html>
