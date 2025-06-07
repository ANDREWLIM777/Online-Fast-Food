document.addEventListener('DOMContentLoaded', () => {
   // Real-time search for stabilization
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const cardGrid = document.querySelector('.card-grid');
    


 // Search function
    const performSearch = () => {
        const searchValue = searchInput ? searchInput.value.trim() : '';
        const params = new URLSearchParams({
            search: searchValue,
            category: categoryFilter.value
        });

        
        // Perform a search only if there is a change
        if (window.location.search !== params.toString()) {
            window.location.search = params.toString();
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 500);
        });
    }

    if (categoryFilter) {
        categoryFilter.addEventListener('change', performSearch);
    }

    // Use event delegates to handle dynamic elements
    cardGrid.addEventListener('click', async (e) => {
        // Handle delete button clicks
        if (e.target.closest('.delete-btn')) {
            e.preventDefault();
            const deleteBtn = e.target.closest('.delete-btn');
            const card = deleteBtn.closest('.neo-card');
            const itemId = card.dataset.id;

            if (!itemId) {
                console.error('No item ID found');
                return;
            }

            const { isConfirmed } = await Swal.fire({
                title: 'Confirm Deletion',
                html: `<p>Delete item <strong>#${itemId}</strong> permanently?</p>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#D64933',
                cancelButtonColor: '#5C6B73',
                confirmButtonText: 'Delete',
                backdrop: 'rgba(0,0,0,0.8)',
                allowOutsideClick: false
            });

            if (isConfirmed) {
                try {
                    const response = await fetch(`delete.php?id=${itemId}`, {
                        method: 'DELETE',
                        headers: {
                            'Content-Type': 'application/json'
                        }
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.error || 'Failed to delete item');
                    }

                    card.classList.add('deleting');
                    
                    await new Promise(resolve => {
                        card.addEventListener('transitionend', resolve, { once: true });
                    });

                    card.remove();

                    await Swal.fire({
                        icon: 'success',
                        title: 'Deleted!',
                        text: 'Item has been removed',
                        showConfirmButton: false,
                        timer: 1500,
                        background: '#2A2B2E',
                        color: '#FDFFFC'
                    });
                } catch (error) {
                    console.error('Delete error:', error);
                    Swal.fire({
                        title: 'Error!',
                        text: error.message,
                        icon: 'error',
                        confirmButtonColor: '#D64933'
                    });
                }
            }
        }
    });

    // Add a card hover effect
    cardGrid.addEventListener('mouseover', (e) => {
        const card = e.target.closest('.neo-card');
        if (card) {
            card.style.transform = 'translateY(-5px)';
            card.style.boxShadow = '0 10px 20px rgba(0,0,0,0.2)';
        }
    }, true);

    cardGrid.addEventListener('mouseout', (e) => {
        const card = e.target.closest('.neo-card');
        if (card) {
            card.style.transform = '';
            card.style.boxShadow = '';
        }
    }, true);
});