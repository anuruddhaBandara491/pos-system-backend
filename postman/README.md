Postman collection for POS Payment API

Files:
- POS Payment API.postman_collection.json - Postman collection (v2.1.0)
- POS Payment API.postman_environment.json - Environment with variables

Quick start:
1. Open Postman and import the collection file `postman/POS Payment API.postman_collection.json`.
2. Import the environment file `postman/POS Payment API.postman_environment.json` and select it.
3. Update the `baseUrl` and `token` environment variables as needed.
4. Use the `Auth - Login (example)` request to get a token (adjust body to your credentials), then run the other requests.

Notes:
- `Idempotency-Key` is auto-generated if not set; you can override it via environment variable `idempotencyKey`.
- The `submit` request will set `paymentId` environment variable if a paymentId is returned.
- Adjust `orderId`, `amount`, and `method` variables to match your test data.
