<?php
// loadingModule.php
function insertLoadingStyles() {
    ?>
    <style>
        .loader-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .loader {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-radius: 50%;
            border-top: 5px solid #007bff;
            animation: spin 1s linear infinite;
        }

        .loader-text {
            position: absolute;
            margin-top: 80px;
            color: #007bff;
            font-weight: bold;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .content-loading {
            display: none;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
    </style>
    <?php
}

function insertLoadingHTML() {
    ?>
    <div class="loader-container" id="loader">
        <div class="loader"></div>
        <div class="loader-text">Loading data...</div>
    </div>
    <?php
}

function insertLoadingScript() {
    ?>
    <script>
    class DataLoader {
        constructor() {
            this.loader = document.getElementById('loader');
            this.initialized = false;
        }

        static getInstance() {
            if (!DataLoader.instance) {
                DataLoader.instance = new DataLoader();
            }
            return DataLoader.instance;
        }

        init(contentId) {
            if (this.initialized) return;
            
            this.content = document.getElementById(contentId);
            if (this.content) {
                this.content.classList.add('content-loading');
            }
            this.initialized = true;
        }

        async startLoading(callback, errorCallback) {
            try {
                if (!this.initialized) {
                    throw new Error('DataLoader not initialized. Call init() first.');
                }

                this.loader.style.display = 'flex';
                if (this.content) {
                    this.content.style.display = 'none';
                }

                // Wait for any async operations
                if (callback && typeof callback === 'function') {
                    await callback();
                }

                // Hide loader and show content
                this.loader.style.display = 'none';
                if (this.content) {
                    this.content.style.display = 'block';
                    this.content.classList.add('fade-in');
                }

            } catch (error) {
                console.error('Error in DataLoader:', error);
                if (errorCallback && typeof errorCallback === 'function') {
                    errorCallback(error);
                } else {
                    alert('An error occurred while loading the data. Please try again.');
                }
            }
        }

        async refreshData(callback, errorCallback) {
            this.loader.style.display = 'flex';
            try {
                if (callback && typeof callback === 'function') {
                    await callback();
                }
            } catch (error) {
                if (errorCallback && typeof errorCallback === 'function') {
                    errorCallback(error);
                } else {
                    console.error('Error refreshing data:', error);
                    alert('Error refreshing data. Please try again.');
                }
            } finally {
                this.loader.style.display = 'none';
            }
        }
    }

    // Make DataLoader available globally
    window.DataLoader = DataLoader;
    </script>
    <?php
}
?>