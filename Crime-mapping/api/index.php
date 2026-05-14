<?php
// Placeholder pa lang to verify API setup. Actual endpoints will be implemented in separate files.
header('Content-Type: application/json');
http_response_code(200);
echo json_encode([
    'status' => 'ok',
    'message' => 'API scaffold ready.'
]);
