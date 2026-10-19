# Helper for writing wiki seed articles: W(slug, category, order, title, summary, body, keywords='', team=False)
import os, textwrap
ROOT = os.path.join(os.path.dirname(__file__), '..', '..', 'crm', 'app', 'wiki-content')
def W(slug, cat, order, title, summary, body, keywords='', team=False):
    d = os.path.join(ROOT, 'team' if team else 'public'); os.makedirs(d, exist_ok=True)
    fm = f"---\ntitle: {title}\ncategory: {cat}\norder: {order}\nsummary: {summary}\n" + (f"keywords: {keywords}\n" if keywords else '') + "---\n"
    open(os.path.join(d, slug + '.md'), 'w').write(fm + textwrap.dedent(body).strip() + "\n")
