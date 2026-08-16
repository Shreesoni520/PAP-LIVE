"use client";

import { useEffect } from "react";
import Script from "next/script";

declare global {
  interface Window {
    AOS?: { init: (options?: Record<string, unknown>) => void };
  }
}

export function SiteScripts() {
  useEffect(() => {
    const timer = window.setTimeout(() => {
      window.AOS?.init?.({
        duration: 600,
        easing: "ease-in-out",
        once: true,
        mirror: false,
      });
    }, 300);

    document.querySelectorAll(".protected-img").forEach((img) => {
      img.addEventListener("contextmenu", (e) => e.preventDefault());
      img.addEventListener("dragstart", (e) => e.preventDefault());
    });

    return () => window.clearTimeout(timer);
  }, []);

  return (
    <>
      <Script
        src="/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"
        strategy="afterInteractive"
      />
      <Script src="/assets/vendor/aos/aos.js" strategy="afterInteractive" />
      <Script
        src="/assets/vendor/glightbox/js/glightbox.min.js"
        strategy="afterInteractive"
      />
      <Script
        src="/assets/vendor/swiper/swiper-bundle.min.js"
        strategy="afterInteractive"
      />
      <Script
        src="/assets/vendor/waypoints/noframework.waypoints.js"
        strategy="afterInteractive"
      />
      <Script
        src="/assets/vendor/imagesloaded/imagesloaded.pkgd.min.js"
        strategy="afterInteractive"
      />
      <Script
        src="/assets/vendor/isotope-layout/isotope.pkgd.min.js"
        strategy="afterInteractive"
      />
      <Script src="/assets/js/main.js" strategy="afterInteractive" />
    </>
  );
}
