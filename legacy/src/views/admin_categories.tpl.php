<h1 class="page-title">Admin: Categories</h1>

<section class="admin-panel">
    <div class="admin-content">
        <h2>Existing Categories</h2>

        <table class="simple-table">
            <thead>
                <tr>
                    <th></th>
                    <th>Title</th>
                    <th>Min Group</th>
                    <th>Order</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                [ !categories_rows ]
            </tbody>
        </table>

        <h2 style="margin-bottom: 0.5rem;">Add New Category</h2>

        <form method="post" class="simple-form">
            <input type="hidden" name="csrf_token" value="[@csrf_token]">

            <div style="display: flex; flex-wrap: wrap; gap: 1rem;">
                <div style="flex: 2;">
                    <label for="title">Title:</label>
                    <input type="text" name="title" id="title" required>

                    <label for="description">Description:</label>
                    <input type="text" name="description" id="description">
                </div>

                <div style="flex: 1;">
                    <label for="min_group">Min Group:</label>
                    <input type="number" name="min_group" id="min_group" value="1" min="1" max="255">

                    <label for="order_id">Order:</label>
                    <input type="number" name="order_id" id="order_id" value="1" min="1">
                </div>
            </div>

            <div style="margin-top: 1rem;">
                <label><input type="checkbox" name="is_visible" value="1" checked> Visible</label>
            </div>

            <div class="form-actions">
                <button type="submit" name="add_category">Add Category</button>
            </div>
        </form>
    </div>
</section>
