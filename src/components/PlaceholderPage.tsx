import Link from "next/link";

export default function PlaceholderPage({
  title,
  description,
}: {
  title: string;
  description: string;
}) {
  return (
    <section className="section">
      <h1 className="section-title">{title}</h1>
      <p className="section-lead">{description}</p>
      <Link href="/" className="btn-secondary">
        Voltar ao início
      </Link>
    </section>
  );
}
