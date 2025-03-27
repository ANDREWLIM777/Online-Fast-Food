// 主内容区交互
document.addEventListener('DOMContentLoaded', () => {
    const cards = document.querySelectorAll('.menu-card');

    // 卡片入场动画：淡入+缩放效果
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        card.style.animation = `cardEntry var(--transition-speed) ease-out ${index * 0.1}s forwards`;
    });

    // 实时搜索功能
    const searchInput = document.getElementById('searchInput');
    searchInput.addEventListener('input', debounce(function() {
        const term = this.value.toLowerCase();
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(term) ? 'block' : 'none';
        });
    }, 300));

    // 删除确认
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('确认删除此项吗？')) {
                e.preventDefault();
            }
        });
    });
});

// 工具函数：防抖
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// 动画关键帧：加入淡入与缩放
const style = document.createElement('style');
style.textContent = `
    @keyframes cardEntry {
        to {
            opacity: 1;
            transform: scale(1);
        }
    }
`;
document.head.appendChild(style);
