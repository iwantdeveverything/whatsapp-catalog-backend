## ADDED Requirements

### PROD-MUT-01: Product Creation
The system MUST expose a `POST /api/products` endpoint to create a new product.
- **Given** a valid set of product attributes (name, category_id, price, currency, status)
- **When** the store endpoint is called
- **Then** the product SHALL be created in the database.
- **And** a URL-friendly, collision-free slug MUST be auto-generated from the product name.
- **And** the endpoint MUST return the created `ProductResource` with a 201 status code.

### PROD-MUT-02: Partial Product Update
The system MUST expose a `PUT/PATCH /api/products/{product}` endpoint to edit a product.
- **Given** an existing product
- **When** the update endpoint is called with a subset (or all) of the product's attributes
- **Then** the product SHALL be updated in the database.
- **And** if the `name` attribute is updated, the slug MUST be regenerated.
- **And** if the `images` array is provided, it MUST completely replace the existing array of image URLs.
- **And** the endpoint MUST return the updated `ProductResource` with a 200 status code.

### PROD-MUT-03: Product Soft-Deletion
The system MUST expose a `DELETE /api/products/{product}` endpoint to remove a product.
- **Given** an existing product
- **When** the destroy endpoint is called
- **Then** the product SHALL be soft-deleted in the database.
- **And** the endpoint MUST return a 204 No Content status code.
- **And** the product MUST NOT appear in the index endpoint, but SHALL remain accessible via the show endpoint (PROD-03).

### PROD-MUT-04: Mutation Authorization
All product mutation endpoints MUST be protected by role-based authorization.
- **Given** an authenticated user
- **When** they attempt to create, update, or delete a product
- **Then** the system MUST check the `ProductPolicy` methods (`create`, `update`, `delete`).
- **Note:** In the current phase, these policy methods SHALL return `true` for any authenticated user.
