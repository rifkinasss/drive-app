import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "Drive by NasLabs",
    short_name: "Drive",
    description: "Private file storage and sharing by NasLabs",
    start_url: "/home",
    scope: "/",
    display: "standalone",
    background_color: "#F4F7FB",
    theme_color: "#F4F7FB",
    icons: [
      {
        src: "/brand/drive-icon-192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/brand/drive-icon-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/brand/drive-icon-maskable-192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "maskable",
      },
      {
        src: "/brand/drive-icon-maskable-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "maskable",
      },
    ],
  };
}
