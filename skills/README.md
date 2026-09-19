# Skills agent — API Merci Facteur

Skills installables pour assistants de développement et agents : Claude Code, Cursor, Codex, Copilot, et les ~75 autres agents reconnus par le CLI [`skills`](https://github.com/vercel-labs/skills).

```bash
npx skills add MerciFacteur/Merci-facteur-API
```

Le CLI copie les skills dans le répertoire de ton agent (`.claude/skills/`, `.agents/skills/`, selon l'agent). Ils se déclenchent ensuite d'eux-mêmes quand tu demandes à ton agent d'envoyer du courrier — sans que tu aies à coller de documentation.

## Les quatre skills

| Skill | Couvre |
|---|---|
| `mf-api-authentification` | signature HMAC-SHA256, `getToken`, durée de vie du token jusqu'à 365 jours, restriction d'IP |
| `mf-envoi-courrier` | `sendCourrier` : lettre PDF, modes d'envoi, adresses, webhooks, idempotence, reprise sur échec |
| `mf-preuves-courrier` | preuves poussées par webhook, `getProof`, statuts de courrier |
| `mf-annuler-envoi` | `deleteEnvoi` : annulation d'un envoi, issues possibles, délai réel |

Chaque skill est autonome. Installer les quatre donne à l'agent la chaîne complète : token → envoi → preuves → annulation.

## Ce que ces skills font, et ne font pas

Ils décrivent **ce que l'API attend**, pas l'architecture de ton application. Ils ne prescrivent ni formulaire, ni tâche planifiée, ni écran : c'est ton besoin qui décide, et tu le dis à ton agent.

Ils imposent en revanche trois choses, parce qu'elles sont la cause de la plupart des intégrations ratées :

- la secret key ne quitte jamais le serveur ;
- chaque appel réussi produit un objet physique facturé et irrécupérable, donc la stratégie de reprise se décide avant d'écrire le code ;
- les noms de champs contre-intuitifs (`adress` avec un seul « d », le vide en `""` et jamais `[]`) ne se « corrigent » pas.

## Autres ressources

- Guides à copier-coller, sans installation : [`ai-guides/`](../ai-guides/)
- Spec OpenAPI : https://www.merci-facteur.com/api/1.2/openapi.json
- Documentation : https://www.merci-facteur.com/api/1.2/
- Classe PHP de référence : [`php-class/`](../php-class/)
- Calcul de la signature dans plusieurs langages : [`hash-secret-key/`](../hash-secret-key/)

## Licence

MIT.
