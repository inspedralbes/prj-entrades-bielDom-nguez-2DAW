## Why

La vista prevista com a **Informes** (evolució temporal + ocupació circular per esdeveniment) no cobreix la necessitat d’una **analítica unificada** amb **filtre temporal global**, comparativa **per esdeveniment** i lectura d’**ocupació agregada per categories**. Substituir aquesta pàgina per **Analítiques** ofereix a l’administrador una lectura coherent del negoci dins del període triat i alinea el producte amb les decisions operatives (ingressos, ritme de venda i ompliment per tipus d’esdeveniment).

## What Changes

- **Substitució de la pàgina Informes**: la ruta i l’entrada de menú d’**Informes** passen a **Analítiques**; el contingut antic d’informes **no** es manté com a vista paral·lela (una sola pàgina d’analítica administrativa en aquest punt del panell).
- **Filtre global de període** (aplica a **totes** les mètriques de la pàgina): presets **7 dies**, **30 dies**, i opció **personalitzada** (interval de dates o “des de” una data, segons es concreti al disseny).
- **Total guanyat**: suma d’**ingressos** (o ingres net segons regla de negoci del repositori) **dins del període filtrat**.
- **Per esdeveniment** (dins del mateix període): **rendiment** en diners generats; **venda mitjana per dia** (mitjana d’ingressos per dia natural dins del període o definició equivalent al pla tècnic).
- **Ocupació mitjana per categoria**: per a cada categoria (p. ex. música), percentatge d’**ocupació mitjana** agregant els esdeveniments d’aquesta categoria (p. ex. si el conjunt de concerts de música ven la meitat del cabuda agregada, es mostra **50 %** per a música). Presentació amb **barra de progrés** i mode alternatiu en **llista** (una fila sota l’altra) mostrant el mateix percentatge de forma llegible.

## Capabilities

### New Capabilities

- `admin-analytics`: Requisits funcionals de la pàgina **Analítiques**: filtres globals de període (7d / 30d / personalitzat), total d’ingressos al període, taula o llista de mètriques **per esdeveniment** (diners generats, venda mitjana per dia), i **ocupació mitjana per categoria** amb dues representacions (barres de progrés i llista vertical) i substitució explícita de l’antiga pàgina d’informes al panell admin.

### Modified Capabilities

- `admin-platform-suite`: La secció **E — Informes i analítica** i el criteri de compliment de la taula resum (“Informes | Línies + circular”) queden **obsolets** respecte a aquest canvi: els requisits d’aquesta àrea passen a descriure **Analítiques** (filtre temporal global, totals, rendiment per esdeveniment, ocupació per categoria) en lloc del parell gràfic de línies + circular centrat en un esdeveniment seleccionat. Cal un **delta** coherent amb `openspec/specs/admin-platform-suite/spec.md`.

## Impact

- **frontend-nuxt**: ruta i components de la zona admin (substitució `informes` → `analítiques` o equivalent kebab-case), navegació del layout admin, components de filtre de dates, taules/llistes i commutador de vista barra/llista per categories.
- **Backend (Laravel)**: nous o estesos endpoints sota prefix `admin` per agregacions per període, per esdeveniment i per categoria; autorització rol `admin`.
- **PostgreSQL**: consultes sobre comandes/tiquets confirmats, enllaç amb esdeveniments i categories; definició exacta d’“ocupació” i cabuda segons el model existent.
- **OpenAPI / contractes**: actualització o delta si el repositori documenta l’API admin.
- **Sense canvi obligatori** a Socket.IO per a aquesta pàgina si les mètriques són **consulta sota demanda** (opcional: refresc en viu en futures iteracions).
