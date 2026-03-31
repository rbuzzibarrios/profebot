# ProfeBot 🦉

Tutor interactivo con IA para estudiantes del currículo cubano. Genera preguntas de opción múltiple, las lee en voz alta y acepta respuestas por voz o toque.

## Grados

### 📚 1° Grado (6-7 años)
- **Matemática** — 7 unidades, 44 objetivos (espacial, números hasta 100, operaciones, geometría)
- **Lengua Española** — 4 unidades, 35 objetivos (fonemas, dígrafos, grafemas, escritura)

### 🧸 Prescolar — 5to y 6to año de vida (5-6 años)
- **Nociones de Matemática** — conjuntos, cantidades, longitudes, resolución de problemas
- **Comunicación y Literatura** — fonética, cuentos, poesías, fábulas, adivinanzas, trabalenguas
- Preguntas simplificadas a 2 opciones (A/B), lenguaje concreto adaptado a la edad

## Características

- Preguntas generadas por IA adaptadas al objetivo y dificultad seleccionados
- Multi-proveedor con fallback automático (Groq, Gemini, Claude)
- Síntesis de voz (TTS) y reconocimiento de voz en español
- Opciones leídas secuencialmente con pausas naturales entre cada una
- Cache de preguntas en servidor para reducir llamadas a la IA
- Materiales de apoyo: subir PDF, pegar texto o URL
- Modos estudio y evaluación con dificultad configurable
- Historial de sesiones con estadísticas
- Diseño mobile-first pensado para niños

## Requisitos

- PHP 7.0+ con extensión cURL
- Navegador con Web Speech API (Chrome/Edge recomendado)
- Al menos una API key: [Groq](https://console.groq.com/keys), [Gemini](https://aistudio.google.com/apikey) o [Claude](https://console.anthropic.com/)

## Uso local

```bash
# Con API keys en variables de entorno
export GROQ_API_KEY=gsk_...
export GEMINI_API_KEY=AIza...
php -S localhost:8080

# O ingresarlas en la UI al abrir la app
php -S localhost:8080
```

Abrir `http://localhost:8080/profebot.php`

## Deploy en Render

1. Crear un **Web Service** con runtime Docker o PHP
2. Configurar variables de entorno: `GROQ_API_KEY`, `GEMINI_API_KEY`
3. Agregar un **Render Disk** montado en `/var/data` (persistencia del cache)
4. El cache de preguntas se guarda en `/var/data/question_cache.json`

## Arquitectura

Monolito en un solo archivo (`profebot.php`):

| Capa | Contenido |
|------|-----------|
| PHP (servidor) | Proxy API multi-proveedor con fallback, cache de preguntas con file locking |
| HTML | 5 pantallas: configuración, materiales, aprendizaje, reporte, historial |
| CSS | Variables custom, mobile-first (640px), Google Fonts (Nunito, Baloo 2) |
| JavaScript | Lógica de app, llamadas API, TTS/STT, gestión de sesiones |

## Cache de preguntas

Las preguntas generadas se guardan en `question_cache.json` indexadas por `objetivo::dificultad`. Al solicitar una pregunta:

1. Busca en cache (excluye las ya preguntadas en la sesión)
2. Si hay pocas en cache (<5), 50% de probabilidad de ir a la IA para aumentar variedad
3. Si no hay en cache, genera con IA y guarda en background
4. Máximo 50 preguntas por clave

## Proveedores de IA

| Proveedor | Modelo | Tipo |
|-----------|--------|------|
| Groq | llama-3.3-70b-versatile | Primario |
| Gemini | gemini-2.5-flash | Fallback |
| Claude | claude-sonnet-4 | Fallback |

La lógica de fallback reintenta con el siguiente proveedor en errores 429/402/403/500/502/503 o errores de red. Detiene en 400/401 (problema de configuración).

## Licencia

Uso educativo.
