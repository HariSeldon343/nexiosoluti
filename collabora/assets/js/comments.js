import app from './app.js';

class CommentsModule {
    constructor() {
        this.comments = [];
    }

    async loadComments(fileId) {
        try {
            const response = await app.authFetch(`api/comments.php?file_id=${fileId}`);
            const data = await response.json();
            this.comments = data.comments || [];
            this.render();
        } catch (error) {
            console.error(error);
        }
    }

    render() {
        const modal = document.getElementById('preview-modal');
        if (!modal) return;
        modal.innerHTML = `
            <div class="modal-content">
                <button class="modal-close" type="button">Chiudi</button>
                <h3>Commenti File</h3>
                ${this.comments.map((comment) => `<div class="file-comment"><strong>${comment.user_name}</strong><p>${comment.comment}</p></div>`).join('')}
            </div>
        `;
        modal.querySelector('.modal-close')?.addEventListener('click', () => modal.classList.add('hidden'));
        modal.classList.remove('hidden');
    }
}

export const commentsModule = new CommentsModule();
export default commentsModule;
