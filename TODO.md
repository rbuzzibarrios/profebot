# TODO

## Pendientes

- [x] **Proteger `question_cache.json` y `materiales/`** — bloqueado vía `router.php` (php -S) y `.htaccess` (Apache). Materiales se leen server-side y se inyectan al system prompt en el proxy; el browser nunca toca `materiales/` directamente.

- [ ] **Agregar más documentación de 1° Grado desde CubaEduca** — usar los scripts Python existentes (`cubaeduca.py`, `findetg-cubaeduca.py`, etc.) para descargar y convertir más materiales del currículo cubano de primaria y agregarlos como TXT en `materiales/`.

- [ ] **Arreglar el acordeón de objetivos** — al hacer clic varias veces en las unidades se solapan (quedan varias abiertas). Implementar comportamiento de acordeón: cerrar la unidad abierta al abrir otra.

- [ ] **NativePHP — llevar la app a móvil** — empaquetar la app para Android/iOS usando NativePHP (PHP nativo), sin depender del navegador.

- [ ] **Panel de administración y configuración** — interfaz para gestionar proveedores, materiales, caché, y configuración general de la app.

- [ ] **Extensión para maestros** — que los profes puedan crear sus propias sesiones personalizadas, definir objetivos y cargar sus propios materiales.

- [ ] **Más grados, más materias, más currículo** — expandir el contenido a más grados de primaria y secundaria con el currículo cubano completo.

- [ ] **Tests** — algún día.

- [ ] **Fallback a caché cuando todos los proveedores fallen** — actualmente si Gemini y Groq fallan (rate limit, 503, etc.), el usuario ve un error. La idea: Gemini como primario (mejor calidad de preguntas), Groq como fallback, y si ambos fallan, servir una pregunta del `question_cache.json` como último recurso. Así el niño nunca se queda sin preguntas. Nota: con Groq las preguntas venían algo raras en calidad, por eso queda como fallback y no como primario.

- [ ] **Mejorar contexto y prompt enviado a los proveedores AI** — actualmente `buildCtx` incluye todos los materiales del grado+materia sin distinción por unidad u objetivo, lo que genera prompts con contexto irrelevante. Potencialmente implementar: (1) mapeo explícito de cada material a las unidades que cubre, para filtrar solo el contexto relevante al objetivo activo; (2) budget total de caracteres distribuido entre las fuentes más relevantes en lugar del límite fijo por archivo. Verificar con DevTools → Network → Payload que el contexto que llega al API es el correcto según grado y materia seleccionados.
