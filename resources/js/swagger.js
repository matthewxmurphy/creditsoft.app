import SwaggerUI from 'swagger-ui-dist/swagger-ui-bundle.js'
import 'swagger-ui-dist/swagger-ui.css'

document.addEventListener('DOMContentLoaded', () => {
    const mount = document.getElementById('swagger-ui')

    if (!mount) {
        return
    }

    const specUrl = mount.dataset.specUrl

    SwaggerUI({
        dom_id: '#swagger-ui',
        url: specUrl,
        deepLinking: true,
        docExpansion: 'list',
        displayRequestDuration: true,
        filter: true,
        persistAuthorization: true,
        presets: [
            SwaggerUI.presets.apis,
        ],
        defaultModelsExpandDepth: -1,
    })
})
