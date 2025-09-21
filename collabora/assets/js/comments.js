class CommentsModule {
    constructor() {
        this.container = document.getElementById('comments-panel');
    }

    async load(fileId) {
        if (!fileId) return;
        const response = await fetch(`api/comments.php?file_id=${fileId}`);
        const data = await response.json();
        this.render(data.comments || []);
    }

    render(comments) {
        if (!this.container) return;
        this.container.innerHTML = comments.map((c) => `
            <div class="comment-item">
                <strong>${c.name}</strong>
                <p>${c.comment}</p>
                <small>${new Date(c.created_at).toLocaleString()}</small>
            </div>
        `).join('');
    }
}

window.commentsModule = new CommentsModule();
