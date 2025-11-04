import React, { useEffect, useState } from "react";
import apiFetch from "@wordpress/api-fetch";

/**
 * Discovery Overlay Component
 * Displays a full-screen overlay when content analysis is needed or in progress.
 *
 * @param {Object} props
 * @param {Object} props.discoveryCheck - Discovery status from dashboard data
 * @param {boolean} props.discoveryCheck.needed - Whether analysis is needed
 * @param {boolean} props.discoveryCheck.discovering - Whether analysis is currently running
 * @param {boolean} props.discoveryCheck.has_posts - Whether posts need analysis
 * @param {boolean} props.discoveryCheck.has_terms - Whether terms need analysis
 */
export const DiscoveryOverlay = ({ discoveryCheck }) => {
  // Poll for status updates when discovery is running
  useEffect(() => {
    if (!discoveryCheck?.needed) {
      return;
    }

    const interval = setInterval(async () => {
      try {
        const status = await apiFetch({ path: "/pllat/v1/discovery/status" });

        if (!status.needed) {
          // Analysis complete - refresh page
          window.location.reload();
        }
      } catch (error) {
        console.error("Error checking discovery status:", error);
      }
    }, 3000); // Check every 3 seconds

    return () => clearInterval(interval);
  }, [discoveryCheck?.needed]);

  // Don't show overlay if analysis not needed
  if (!discoveryCheck?.needed) {
    return null;
  }

  return (
    <div
      className="fixed inset-0 flex items-center justify-center"
      style={{ zIndex: 100000 }}
    >
      {/* Backdrop */}
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />

      {/* Modal */}
      <div
        className="relative bg-white rounded-lg shadow-2xl max-w-lg w-full mx-4"
        style={{ padding: "2.5rem", border: "1px solid #dcdcde" }}
      >
        {/* Always show analyzing state - discovery runs automatically */}
        <div className="flex justify-center mb-6">
          <span
            className="spinner is-active"
            style={{
              float: "none",
              width: "40px",
              height: "40px",
              margin: "0",
            }}
          ></span>
        </div>

        <h2
          className="text-center mb-4"
          style={{
            fontSize: "22px",
            fontWeight: "600",
            color: "#1d2327",
            margin: "0 0 16px 0",
          }}
        >
          Analyzing Your Content...
        </h2>

        <p
          className="text-center mb-6"
          style={{
            fontSize: "14px",
            lineHeight: "1.6",
            color: "#50575e",
            margin: "0 0 24px 0",
          }}
        >
          We are analyzing your content for translations. This usually takes
          30-60 seconds.
        </p>

        <div
          style={{
            backgroundColor: "#f0f6fc",
            border: "1px solid #c3e4f7",
            borderRadius: "4px",
            padding: "12px 16px",
            marginBottom: "24px",
          }}
        >
          <p
            style={{
              fontSize: "13px",
              color: "#2c3338",
              margin: 0,
              display: "flex",
              alignItems: "flex-start",
              gap: "8px",
            }}
          >
            <span style={{ flexShrink: 0 }}>💡</span>
            <span>
              Discovery runs automatically every 30 seconds. You can safely close this page.
            </span>
          </p>
        </div>

        <button
          onClick={() => window.location.reload()}
          className="button button-secondary"
          style={{
            width: "100%",
            textAlign: "center",
            justifyContent: "center",
            display: "flex",
            alignItems: "center",
          }}
        >
          Refresh Status
        </button>

        {/* Optional: Technical details */}
        <details
          className="mt-6 pt-4"
          style={{ borderTop: "1px solid #dcdcde" }}
        >
          <summary
            className="text-xs cursor-pointer font-medium"
            style={{ color: "#646970" }}
          >
            Technical information
          </summary>
          <ul
            className="mt-2 text-xs pl-4 list-disc"
            style={{ color: "#646970" }}
          >
            <li>
              Posts need analysis: {discoveryCheck.has_posts ? "Yes" : "No"}
            </li>
            <li>
              Categories need analysis:{" "}
              {discoveryCheck.has_terms ? "Yes" : "No"}
            </li>
          </ul>
        </details>
      </div>
    </div>
  );
};
