# Checklist de qualitat de requisits (ticketing): Mapa de seients, bloquejos, entrades i validació

**Purpose**: “Unit tests” del redactat dels requisits (completitud, claredat, coherència, mesurabilitat) — **no** són proves d’implementació.  
**Created**: 2026-04-11  
**Feature**: [spec.md](../spec.md) · context [plan.md](../plan.md)

**Supòsits d’aquesta execució** (sense `$ARGUMENTS` addicionals): profunditat **estàndard**; audiència **autor del spec + revisor de PR**; focus **integració de catàleg, hold concurrent, credencials per seient, validació només online, UI post-validació**.

---

## Requirement Completeness

- [ ] CHK001 - Estan documentats els requisits per a **totes** les fallades o absències del catàleg Top Picks (imatge o zones) sense inventar dades, més enllà del missatge clar? [Completeness, Spec §Edge Cases, FR-001]
- [ ] CHK002 - El spec defineix explícitament el que passa quan **no** queden seients suficients per repetir la selecció després d’una expiració de hold? [Gap, Spec §US1 escenari 4, Edge Cases últim bullet]
- [ ] CHK003 - Estan els requisits de **pagament / confirmació de comanda** com a prerequisit per emetre credencials vinculats a un flux documentat (encara que sigui fora d’aquest spec)? [Completeness, Gap — FR-005 parla de “seient confirmat”]
- [ ] CHK004 - La identitat i els permisos del **validador** (rol, vincle a esdeveniment o porta) estan especificats com a requisit o queden només com a “rol validador”? [Completeness, Spec §Key Entities Validador, FR-007]

## Requirement Clarity

- [ ] CHK005 - “**Marca visual clara**” i “**X** o equivalent” (FR-008, US3) estan prou delimitats per poder derivar criteris de disseny sense reinterpretar “clar”? [Clarity, Spec §FR-008, referència [22, Historial]]
- [ ] CHK006 - La **tolerància de segons** entre compte enrere mostrat i expiració real (SC-002) està fixada com a requisit quantificat o roman “definida al pla”? [Clarity, Ambiguity, Spec §SC-002]
- [ ] CHK007 - “**Codi caducat**” (Edge Cases) té definició de requisit (TTL del JWT, revocació, fi d’esdeveniment)? [Clarity, Gap, Spec §Edge Cases bullet reutilització]

## Requirement Consistency

- [ ] CHK008 - Els requisits de **una credencial per seient** (Clarifications, FR-005) són coherents amb SC-005 (sis escaneigs successius) sense contradir un flux de grup? [Consistency, Spec §FR-005, SC-005]
- [ ] CHK009 - La regla de **pròrroga** (FR-003: sense pròrroga genèrica, **+2 min només des del checkout** en iniciar login) és consistent amb US1, Edge Cases i Session 2026-04-12 sense contradicions residuals? [Consistency, Spec §FR-003, US1 escenaris 4–6]

## Acceptance Criteria Quality

- [ ] CHK010 - Els percentatges (95%, 100%) dels SC poden verificar-se sense definir **mida de mostra** o entorn de prova en un requisit separat? [Measurability, Gap, Spec §SC-002–SC-004]
- [ ] CHK011 - El límit “**~60 segons**” de SC-005 està justificat com a acord de proves o cal una métrica més estable al text de requisits? [Clarity, Spec §SC-005]

## Scenario Coverage

- [ ] CHK012 - Estan coberts els requisits per al flux **primari** comprador → hold → compra → credencial → validació → UI usat? [Coverage, Spec §US1–US3]
- [ ] CHK013 - El flux **alternatiu** “només algunes entrades del grup validades” té requisits explícits d’experiència d’usuari (p. ex. ordre al llistat) o només esment de parcialitat? [Coverage, Spec §US3, Edge Cases compra múltiple]
- [ ] CHK014 - Els requisits d’**error/excepció** per a validació (latència, sense xarxa) especifiquen el comportament esperat del **missatge** al validador de forma revisable? [Coverage, Spec §US3 escenari 4, Edge Cases validació]

## Edge Case Coverage

- [ ] CHK015 - La competència entre dos holds sobre el mateix seient està coberta per requisits que coincideixin amb FR-002 (atòmic tot o res)? [Consistency, Spec §Edge Cases primer bullet, FR-002]
- [ ] CHK016 - Estan els requisits per a **reutilització fraudulenta** (captura, duplicat) alineats amb “només servidor verifica” sense forats entre FR-007 i Edge Cases? [Coverage, Spec §FR-007, Edge Cases]

## Non-Functional Requirements (requisit del redactat)

- [ ] CHK017 - El spec documenta requisits de **disponibilitat** del servei de validació o només escenaris puntuals (latència/sense xarxa)? [Gap, NFR]
- [ ] CHK018 - Hi ha requisits explícits de **privacitat / dades personals** en context de QR i historial, o només assumides fora del spec? [Gap, NFR]

## Dependencies & Assumptions

- [ ] CHK019 - L’assumpció de **llicència Ticketmaster** (Assumptions) està reflectida com a dependència de negoci traçable en els FR? [Traceability, Spec §Assumptions, FR-001]
- [ ] CHK020 - La dependència **node-qrcode v1.5** (Assumptions) entra en tensió amb FR-006 (“pila fixada al pla”) i cal una frase de requisit que uneixi spec i pla sense ambigüitat? [Consistency, Spec §FR-006, Assumptions]

## Ambiguities & Conflicts

- [ ] CHK021 - El terme “**assistència**” vs “comprador” / “usuari” és usat de forma consistent al llarg de totes les històries i FR? [Consistency, Terminology]
- [ ] CHK022 - Existeix conflicte latent entre “**historial**” (FR-008) i qualsevol requisit futur d’anonimització o esborrat que calgui anticipar? [Conflict, Gap, Spec §FR-008]

## Notes

- Desmarcar ítems mentre es revisa el `spec.md`; cap ítem valida codi, només la qualitat del requisit escrit.
- Referències `[Spec §…]` apunten a seccions/IDs del `spec.md` actual.
