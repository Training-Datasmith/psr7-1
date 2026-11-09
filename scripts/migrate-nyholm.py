#!/usr/bin/env python3
"""Apply Studly_Snake class and repo-owned identifier renames for nyholm/psr7."""
from __future__ import annotations

import re
import subprocess
from pathlib import Path

REV = "889d890"
ROOT = Path(__file__).resolve().parent.parent

CLASS_MAP = {
    "HttplugFactory": "Httplug_Factory",
    "Psr17Factory": "Psr17_Factory",
    "MessageTrait": "Message_Trait",
    "RequestTrait": "Request_Trait",
    "ServerRequest": "Server_Request",
    "StreamTrait": "Stream_Trait",
    "UploadedFile": "Uploaded_File",
}

TEST_CLASS_MAP = {
    "HttplugFactoryTest": "Httplug_Factory_Test",
    "Psr17FactoryTest": "Psr17_Factory_Test",
    "RequestTest": "Request_Test",
    "ResponseTest": "Response_Test",
    "ServerRequestTest": "Server_Request_Test",
    "StreamTest": "Stream_Test",
    "UploadedFileTest": "Uploaded_File_Test",
    "UriTest": "Uri_Test",
    "TestStreamWrapper": "Test_Stream_Wrapper",
}

REPO_METHOD_MAP = {
    "setHeaders": "set_headers",
    "validateAndTrimHeader": "validate_and_trim_header",
    "updateHostFromUri": "update_host_from_uri",
    "getUri": "get_uri",
    "openZvalStream": "open_zval_stream",
    "validateActive": "validate_active",
    "createUriString": "create_uri_string",
    "isNonStandardPort": "is_non_standard_port",
    "filterPort": "filter_port",
    "filterPath": "filter_path",
    "filterQueryAndFragment": "filter_query_and_fragment",
    "rawurlencodeMatchZero": "rawurlencode_match_zero",
}

PROP_MAP = {
    "headerNames": "header_names",
    "requestTarget": "request_target",
    "reasonPhrase": "reason_phrase",
    "statusCode": "status_code",
    "cookieParams": "cookie_params",
    "parsedBody": "parsed_body",
    "queryParams": "query_params",
    "serverParams": "server_params",
    "uploadedFiles": "uploaded_files",
    "clientFilename": "client_filename",
    "clientMediaType": "client_media_type",
    "userInfo": "user_info",
    "streamOrFile": "stream_or_file",
    "errorStatus": "error_status",
    "errorHandler": "error_handler",
    "invalidUri": "invalid_uri",
    "expectedMessage": "expected_message",
    "mediaType": "media_type",
    "requestWithoutAttribute": "request_without_attribute",
    "uploadedFile": "uploaded_file",
    "uploadStream": "upload_stream",
    "testDomain": "test_domain",
    "headerValue": "header_value",
}


def git_show(path: str) -> str:
    return subprocess.check_output(["git", "show", f"{REV}:{path}"], text=True, cwd=ROOT)


def path_map(old_path: str) -> str:
    stem = Path(old_path).stem
    if stem in CLASS_MAP:
        return str(Path(old_path).parent / (CLASS_MAP[stem] + ".php"))
    if stem in TEST_CLASS_MAP:
        return str(Path(old_path).parent / (TEST_CLASS_MAP[stem] + ".php"))
    return old_path


def camel_to_snake(name: str) -> str:
    return re.sub(r"(?<!^)(?=[A-Z])", "_", name).lower()


def rename_test_methods(text: str) -> str:
    hooks = {
        "setUp", "tearDown", "setUpBeforeClass", "tearDownAfterClass",
        "assertPreConditions", "assertPostConditions", "onNotSuccessfulTest", "createSubject",
    }

    def replace_def(match: re.Match[str]) -> str:
        name = match.group(1)
        if name in hooks:
            return f"function {name}"
        if name.startswith("test") and len(name) > 4:
            return f"function test_{camel_to_snake(name[4:])}"
        if name.startswith("provide") and len(name) > 7:
            return f"function provide_{camel_to_snake(name[7:])}"
        if re.match(r"^(get|invalid|trimmed|nonOk|uriComponents)", name):
            return f"function {camel_to_snake(name)}"
        return match.group(0)

    text = re.sub(r"function (test\w+|provide\w+|get\w+|invalid\w+|trimmed\w+|nonOk\w+|uriComponents\w+)", replace_def, text)

    def replace_dp(match: re.Match[str]) -> str:
        return f"@dataProvider {camel_to_snake(match.group(1))}"

    return re.sub(r"@dataProvider\s+(\w+)", replace_dp, text)


def rename_repo_methods(text: str, *, defs: bool) -> str:
    for old, new in sorted(REPO_METHOD_MAP.items(), key=lambda x: -len(x[0])):
        if defs:
            text = re.sub(rf"\bfunction\s+{re.escape(old)}\s*\(", f"function {new}(", text)
        text = re.sub(rf"::{re.escape(old)}\s*\(", f"::{new}(", text)
        text = re.sub(rf"->{re.escape(old)}\s*\(", f"->{new}(", text)
        text = re.sub(rf"\$this->{re.escape(old)}\s*\(", f"$this->{new}(", text)
    return text


def rename_props(text: str) -> str:
    for old, new in sorted(PROP_MAP.items(), key=lambda x: -len(x[0])):
        text = re.sub(rf"\${re.escape(old)}\b", f"${new}", text)
        text = re.sub(rf"->{re.escape(old)}\b", f"->{new}", text)
        text = re.sub(rf"(\W){re.escape(old)}(\s*=)", rf"\1{new}\2", text)
    return text


def apply_class_map(text: str) -> str:
    for old, new in sorted({**CLASS_MAP, **TEST_CLASS_MAP}.items(), key=lambda x: -len(x[0])):
        text = text.replace(old, new)
    return text


def transform(content: str, *, is_test: bool) -> str:
    content = apply_class_map(content)
    content = rename_props(content)
    content = rename_repo_methods(content, defs=not is_test)
    if is_test:
        content = rename_test_methods(content)
    content = content.replace(
        'use "Nyholm\\Psr7\\Factory\\Psr17Factory" instead',
        'use "Nyholm\\Psr7\\Factory\\Psr17_Factory" instead',
    )
    content = content.replace("HttplugFactory", "Httplug_Factory")
    if is_test and "UriTest" in content or "Uri_Test" in content:
        content = re.sub(
            r"\s*if \(\\version_compare\(\\PHP_VERSION, '7\.4\.12'\) < 0\) \{\s*self::markTestSkipped\([^)]+\);\s*\}\s*\n",
            "\n",
            content,
        )
    return content


def main() -> None:
    old_src = subprocess.check_output(
        ["git", "ls-tree", "-r", "--name-only", REV, "src"], text=True, cwd=ROOT
    ).splitlines()
    old_tests = subprocess.check_output(
        ["git", "ls-tree", "-r", "--name-only", REV, "tests"], text=True, cwd=ROOT
    ).splitlines()

    written: list[Path] = []
    for old in old_src:
        if not old.endswith(".php"):
            continue
        new = path_map(old)
        content = transform(git_show(old), is_test=False)
        out = ROOT / new
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(content)
        written.append(out)

    for old in old_tests:
        if not old.endswith(".php"):
            continue
        new = path_map(old)
        content = transform(git_show(old), is_test=True)
        out = ROOT / new
        out.parent.mkdir(parents=True, exist_ok=True)
        out.write_text(content)
        written.append(out)

  # Remove obsolete paths
    for old in old_src + old_tests:
        if not old.endswith(".php"):
            continue
        new = path_map(old)
        if new != old:
            obsolete = ROOT / old
            if obsolete.exists():
                obsolete.unlink()

    extras = [
        ("phpunit.xml.dist", lambda t: t.replace("Psr17Factory", "Psr17_Factory")),
        ("phpstan-baseline.neon", lambda t: t.replace("ServerRequest.php", "Server_Request.php").replace("UploadedFile.php", "Uploaded_File.php")),
        ("README.md", transform_docs),
        ("CHANGELOG.md", apply_class_map),
    ]
    for name, fn in extras:
        path = ROOT / name
        if path.exists():
            path.write_text(fn(path.read_text()))

    print("Migrated", len(written), "PHP files.")


def transform_docs(text: str) -> str:
    text = apply_class_map(text)
    text = text.replace("Psr17Factory", "Psr17_Factory")
    return text


if __name__ == "__main__":
    main()
